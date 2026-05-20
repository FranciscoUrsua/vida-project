<?php

namespace Modules\Agenda\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Enums\MotivoReasignacion;
use Modules\Agenda\Enums\OrigenCita;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\ExcepcionProfesional;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\LineaCuadrante;
use Modules\Agenda\Models\ReasignacionCita;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Models\TipoSlot;
use Modules\Agenda\Services\GestionAusenciaService;
use Modules\Centro\Models\Centro;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NoShowProfesionalTest extends TestCase
{
    use RefreshDatabase;

    private function crearCentro(string $nombre = 'Centro de Prueba'): Centro
    {
        return Centro::create([
            'nombre'       => $nombre,
            'tipo_gestion' => 'municipal_directo',
            'fecha_alta'   => now()->toDateString(),
        ]);
    }

    private function crearHorario(Centro $centro, string $modo = 'estandar'): HorarioCentro
    {
        return HorarioCentro::create([
            'centro_id'             => $centro->id,
            'nombre'                => 'Horario',
            'dias_laborables'       => [1, 2, 3, 4, 5],
            'hora_apertura'         => '08:00',
            'hora_cierre'           => '19:00',
            'hora_inicio_atencion'  => '09:00',
            'hora_fin_atencion'     => '14:00',
            'buffer_inicio_minutos' => 0,
            'buffer_fin_minutos'    => 0,
            'vigente_desde'         => '2026-01-01',
            'vigente_hasta'         => null,
            'modo_agenda'           => $modo,
            'activo'                => true,
        ]);
    }

    /** Crea una Cita confirmada sobre un slot disponible (el observer marca el slot como reservado). */
    private function crearCita(Slot $slot): Cita
    {
        return Cita::create([
            'slot_id'        => $slot->id,
            'ciudadano_id'   => \App\Models\Ciudadano::factory()->create()->id,
            'profesional_id' => $slot->usuario_id,
            'tipo_slot_id'   => $slot->tipo_slot_id,
            'centro_id'      => $slot->centro_id,
            'fecha'          => $slot->fecha->toDateString(),
            'hora_inicio'    => $slot->hora_inicio,
            'hora_fin'       => $slot->hora_fin,
            'estado'         => EstadoCita::Confirmada->value,
            'origen'         => OrigenCita::Interno->value,
        ]);
    }

    // =========================================================================
    // PF-07.1 — Registrar ausencia sobrevenida cancela citas del día
    // =========================================================================

    #[Test]
    public function test_pf_07_1_ausencia_cancela_citas_con_motivo(): void
    {
        $centro      = $this->crearCentro();
        $this->crearHorario($centro, 'estandar');
        $profesional = User::factory()->create();
        $sustituto   = User::factory()->create();
        $fecha       = now()->toDateString();

        // Tres citas confirmadas del profesional hoy en este centro
        $slots = Slot::factory()->count(3)->create([
            'usuario_id' => $profesional->id,
            'centro_id'  => $centro->id,
            'fecha'      => $fecha,
        ]);
        foreach ($slots as $slot) {
            $this->crearCita($slot);
        }

        // Slot de urgencia de otro profesional (candidato para reasignación)
        $slotUrgencia = Slot::factory()->urgencia()->create([
            'usuario_id' => $sustituto->id,
            'centro_id'  => $centro->id,
            'fecha'      => $fecha,
        ]);

        $resultado = (new GestionAusenciaService())
            ->procesarAusencia($profesional->id, $centro->id, now());

        // Las tres citas deben haber pasado a cancelada con motivo descriptivo
        $this->assertCount(3, $resultado['citas']);
        $citasCanceladas = Cita::where('profesional_id', $profesional->id)
            ->where('estado', EstadoCita::Cancelada->value)
            ->get();
        $this->assertCount(3, $citasCanceladas);
        foreach ($citasCanceladas as $cita) {
            $this->assertStringContainsString('Ausencia', $cita->motivo_cancelacion);
        }

        // El servicio devuelve el slot de urgencia como candidato
        $this->assertCount(1, $resultado['candidatos']);
        $this->assertEquals($slotUrgencia->id, $resultado['candidatos']->first()->id);
    }

    // =========================================================================
    // PF-07.2 — La reasignación a slot de urgencia crea el registro de reasignación
    // =========================================================================

    #[Test]
    public function test_pf_07_2_reasignacion_a_urgencia_crea_registro(): void
    {
        $profesionalOriginal = User::factory()->create();
        $profesionalNuevo    = User::factory()->create();
        $supervisor          = User::factory()->create();

        // Cita original (cancelada por ausencia del profesional)
        $slotOriginal = Slot::factory()->reservado()->create([
            'usuario_id' => $profesionalOriginal->id,
        ]);
        $cita = Cita::create([
            'slot_id'        => $slotOriginal->id,
            'ciudadano_id'   => \App\Models\Ciudadano::factory()->create()->id,
            'profesional_id' => $profesionalOriginal->id,
            'tipo_slot_id'   => $slotOriginal->tipo_slot_id,
            'centro_id'      => $slotOriginal->centro_id,
            'fecha'          => $slotOriginal->fecha->toDateString(),
            'hora_inicio'    => $slotOriginal->hora_inicio,
            'hora_fin'       => $slotOriginal->hora_fin,
            'estado'         => EstadoCita::Cancelada->value,
            'origen'         => OrigenCita::Interno->value,
        ]);

        // Slot de urgencia disponible del profesional sustituto
        $slotUrgencia = Slot::factory()->urgencia()->create([
            'usuario_id' => $profesionalNuevo->id,
        ]);

        $reasignacion = (new GestionAusenciaService())->reasignar(
            $cita,
            $slotUrgencia,
            $supervisor->id,
            MotivoReasignacion::BajaSobrevenida->value
        );

        // Se crea el registro de reasignación con los vínculos correctos
        $this->assertInstanceOf(ReasignacionCita::class, $reasignacion);
        $this->assertEquals($cita->id, $reasignacion->cita_id);
        $this->assertEquals($slotOriginal->id, $reasignacion->slot_original_id);
        $this->assertEquals($slotUrgencia->id, $reasignacion->slot_nuevo_id);
        $this->assertEquals($profesionalOriginal->id, $reasignacion->profesional_original_id);
        $this->assertEquals($profesionalNuevo->id, $reasignacion->profesional_nuevo_id);
        $this->assertEquals($supervisor->id, $reasignacion->realizada_por_id);

        // La cita queda confirmada con el nuevo profesional
        $citaFresh = $cita->fresh();
        $this->assertEquals(EstadoCita::Confirmada, $citaFresh->estado);
        $this->assertEquals($profesionalNuevo->id, $citaFresh->profesional_id);

        // El slot de urgencia queda reservado
        $this->assertEquals(EstadoSlot::Reservado, $slotUrgencia->fresh()->estado);
    }

    // =========================================================================
    // PF-07.3 — Sin slots de urgencia disponibles devuelve colección vacía
    // =========================================================================

    #[Test]
    public function test_pf_07_3_sin_urgencias_disponibles_devuelve_vacio(): void
    {
        $centro      = $this->crearCentro();
        $this->crearHorario($centro, 'estandar');
        $profesional = User::factory()->create();
        $fecha       = now()->toDateString();

        // Una cita del profesional hoy
        $slot = Slot::factory()->create([
            'usuario_id' => $profesional->id,
            'centro_id'  => $centro->id,
            'fecha'      => $fecha,
        ]);
        $this->crearCita($slot);

        // No se crean slots de urgencia en este centro para esta fecha

        $resultado = (new GestionAusenciaService())
            ->procesarAusencia($profesional->id, $centro->id, now());

        // La cita se cancela igualmente
        $this->assertCount(1, $resultado['citas']);
        $this->assertEquals(
            EstadoCita::Cancelada,
            $resultado['citas']->first()->fresh()->estado
        );

        // No hay candidatos de urgencia disponibles
        $this->assertTrue(
            $resultado['candidatos']->isEmpty(),
            'Sin slots de urgencia el servicio debe devolver candidatos vacíos'
        );
    }

    // =========================================================================
    // PF-07.4 — En modo básico la reasignación usa slots ordinarios
    // =========================================================================

    #[Test]
    public function test_pf_07_4_modo_basico_usa_slots_ordinarios(): void
    {
        $centro      = $this->crearCentro();
        $this->crearHorario($centro, 'basico');
        $profesional = User::factory()->create();
        $sustituto   = User::factory()->create();
        $fecha       = now()->toDateString();

        // Cita del profesional hoy
        $slot = Slot::factory()->create([
            'usuario_id' => $profesional->id,
            'centro_id'  => $centro->id,
            'fecha'      => $fecha,
        ]);
        $this->crearCita($slot);

        // Slot ordinario (disponible) del sustituto — en básico no hay urgencias
        $slotDisponible = Slot::factory()->create([
            'usuario_id' => $sustituto->id,
            'centro_id'  => $centro->id,
            'fecha'      => $fecha,
        ]);

        $resultado = (new GestionAusenciaService())
            ->procesarAusencia($profesional->id, $centro->id, now());

        // La cita se cancela
        $this->assertCount(1, $resultado['citas']);

        // En modo básico los candidatos son slots disponibles ordinarios, no urgencias
        $this->assertCount(1, $resultado['candidatos']);
        $this->assertEquals($slotDisponible->id, $resultado['candidatos']->first()->id);
        $this->assertEquals(EstadoSlot::Disponible, $resultado['candidatos']->first()->estado);
    }

    // =========================================================================
    // PF-07.5 — Una excepción posterior anula las líneas y slots materializados
    // =========================================================================

    #[Test]
    public function test_pf_07_5_excepcion_posterior_anula_lineas_y_slots(): void
    {
        $centro      = $this->crearCentro();
        $supervisor  = User::factory()->create();
        $profesional = User::factory()->create();

        // Cuadrante publicado con líneas y slots materializados para junio 2026
        $horario  = $this->crearHorario($centro, 'estandar');
        $tipoSlot = TipoSlot::create([
            'horario_centro_id'        => $horario->id,
            'nombre'                   => 'Cita',
            'duracion_minutos'         => 60,
            'requiere_espacio'         => false,
            'porcentaje_urgencias'     => 0,
            'origen_permitido'         => 'ambos',
            'genera_apunte_automatico' => false,
            'activo'                   => true,
        ]);

        $cuadrante = CuadranteMes::create([
            'centro_id'                => $centro->id,
            'anyo'                     => 2026,
            'mes'                      => 6,
            'estado'                   => EstadoCuadrante::Publicado->value,
            'generado_con_ia'          => false,
            'generado_automaticamente' => false,
            'publicado_en'             => now(),
            'publicado_por_id'         => $supervisor->id,
        ]);

        // Línea del 15 de junio (fuera del rango de la excepción)
        $linea15 = LineaCuadrante::create([
            'cuadrante_mes_id' => $cuadrante->id,
            'usuario_id'       => $profesional->id,
            'centro_id'        => $centro->id,
            'fecha'            => '2026-06-15',
            'franjas'          => [['inicio' => '09:00', 'fin' => '14:00']],
            'anulada'          => false,
        ]);
        $slot15 = Slot::create([
            'linea_cuadrante_id' => $linea15->id,
            'usuario_id'         => $profesional->id,
            'centro_id'          => $centro->id,
            'tipo_slot_id'       => $tipoSlot->id,
            'fecha'              => '2026-06-15',
            'hora_inicio'        => '09:00',
            'hora_fin'           => '10:00',
            'estado'             => EstadoSlot::Disponible->value,
        ]);

        // Línea del 22 de junio (dentro del rango, con cita confirmada)
        $linea22 = LineaCuadrante::create([
            'cuadrante_mes_id' => $cuadrante->id,
            'usuario_id'       => $profesional->id,
            'centro_id'        => $centro->id,
            'fecha'            => '2026-06-22',
            'franjas'          => [['inicio' => '09:00', 'fin' => '14:00']],
            'anulada'          => false,
        ]);
        // Creamos el slot directamente como disponible y luego la cita vía observer
        $slot22 = Slot::create([
            'linea_cuadrante_id' => $linea22->id,
            'usuario_id'         => $profesional->id,
            'centro_id'          => $centro->id,
            'tipo_slot_id'       => $tipoSlot->id,
            'fecha'              => '2026-06-22',
            'hora_inicio'        => '09:00',
            'hora_fin'           => '10:00',
            'estado'             => EstadoSlot::Disponible->value,
        ]);
        // Al crear la cita, el CitaObserver marca slot22 como reservado
        $cita = Cita::create([
            'slot_id'        => $slot22->id,
            'ciudadano_id'   => \App\Models\Ciudadano::factory()->create()->id,
            'profesional_id' => $profesional->id,
            'tipo_slot_id'   => $tipoSlot->id,
            'centro_id'      => $centro->id,
            'fecha'          => '2026-06-22',
            'hora_inicio'    => '09:00',
            'hora_fin'       => '10:00',
            'estado'         => EstadoCita::Confirmada->value,
            'origen'         => OrigenCita::Interno->value,
        ]);

        // Línea del 23 de junio (dentro del rango, sin cita, slot disponible)
        $linea23 = LineaCuadrante::create([
            'cuadrante_mes_id' => $cuadrante->id,
            'usuario_id'       => $profesional->id,
            'centro_id'        => $centro->id,
            'fecha'            => '2026-06-23',
            'franjas'          => [['inicio' => '09:00', 'fin' => '14:00']],
            'anulada'          => false,
        ]);
        $slot23 = Slot::create([
            'linea_cuadrante_id' => $linea23->id,
            'usuario_id'         => $profesional->id,
            'centro_id'          => $centro->id,
            'tipo_slot_id'       => $tipoSlot->id,
            'fecha'              => '2026-06-23',
            'hora_inicio'        => '09:00',
            'hora_fin'           => '10:00',
            'estado'             => EstadoSlot::Disponible->value,
        ]);

        // Baja médica registrada el día 15 para los días 20-30
        // El ExcepcionProfesionalObserver propaga el efecto sobre el cuadrante
        ExcepcionProfesional::create([
            'usuario_id'            => $profesional->id,
            'centro_id'             => $centro->id,
            'tipo'                  => 'baja_medica',
            'fecha_inicio'          => '2026-06-20',
            'fecha_fin'             => '2026-06-30',
            'afecta_disponibilidad' => true,
            'franja_afectada'       => null,
            'origen'                => 'manual',
            'creado_por_id'         => $supervisor->id,
        ]);

        // Líneas dentro del rango → anuladas con referencia a la excepción
        $this->assertTrue($linea22->fresh()->anulada, 'Línea del 22 debe estar anulada');
        $this->assertTrue($linea23->fresh()->anulada, 'Línea del 23 debe estar anulada');
        $this->assertNotNull($linea22->fresh()->excepcion_id, 'Debe referenciar la excepción');

        // Línea fuera del rango → intacta
        $this->assertFalse($linea15->fresh()->anulada, 'Línea del 15 (fuera del rango) no debe anularse');

        // Slot con cita (reservado) → no se anula; la cita sí se cancela
        $this->assertEquals(EstadoSlot::Reservado, $slot22->fresh()->estado, 'Slot reservado no se anula');
        $this->assertEquals(EstadoCita::Cancelada, $cita->fresh()->estado, 'Cita confirmada debe cancelarse');
        $this->assertEquals('Excepción del profesional', $cita->fresh()->motivo_cancelacion);

        // Slot disponible sin cita → anulado
        $this->assertEquals(EstadoSlot::Anulado, $slot23->fresh()->estado, 'Slot disponible debe anularse');

        // Slot fuera del rango → intacto
        $this->assertEquals(EstadoSlot::Disponible, $slot15->fresh()->estado, 'Slot del 15 no debe anularse');
    }
}
