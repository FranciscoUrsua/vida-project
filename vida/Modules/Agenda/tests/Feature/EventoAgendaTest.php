<?php

namespace Modules\Agenda\Tests\Feature;

use App\Models\Ciudadano;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\EventoAgenda;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\LineaCuadrante;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Models\TipoSlot;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\ColeccionPlazas;
use Modules\Centro\Models\Espacio;
use Modules\Centro\Models\TipoEspacio;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventoAgendaTest extends TestCase
{
    use RefreshDatabase;

    private function crearCentro(string $nombre = 'Centro de Prueba', string $modo = 'estandar'): Centro
    {
        return Centro::create([
            'nombre' => $nombre,
            'tipo_gestion' => 'municipal_directo',
            'fecha_alta' => now()->toDateString(),
        ]);
    }

    private function crearHorario(Centro $centro, string $modo = 'estandar'): HorarioCentro
    {
        return HorarioCentro::create([
            'centro_id' => $centro->id,
            'nombre' => 'Horario '.$centro->nombre,
            'dias_laborables' => [1, 2, 3, 4, 5],
            'hora_apertura' => '08:00',
            'hora_cierre' => '18:00',
            'hora_inicio_atencion' => '09:00',
            'hora_fin_atencion' => '14:00',
            'buffer_inicio_minutos' => 0,
            'buffer_fin_minutos' => 0,
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
            'modo_agenda' => $modo,
            'activo' => true,
        ]);
    }

    private function crearTipoSlot(HorarioCentro $horario): TipoSlot
    {
        return TipoSlot::create([
            'horario_centro_id' => $horario->id,
            'nombre' => 'Cita',
            'duracion_minutos' => 60,
            'requiere_espacio' => false,
            'porcentaje_urgencias' => 0,
            'origen_permitido' => 'ambos',
            'genera_apunte_automatico' => false,
            'activo' => true,
        ]);
    }

    private function crearSlotDisponible(User $usuario, Centro $centro, TipoSlot $tipoSlot, LineaCuadrante $linea, string $horaInicio, string $horaFin): Slot
    {
        return Slot::create([
            'linea_cuadrante_id' => $linea->id,
            'usuario_id' => $usuario->id,
            'centro_id' => $centro->id,
            'tipo_slot_id' => $tipoSlot->id,
            'fecha' => '2026-06-10',
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'estado' => EstadoSlot::Disponible->value,
        ]);
    }

    private function crearLineaCuadrante(CuadranteMes $cuadrante, User $usuario, Centro $centro): LineaCuadrante
    {
        return LineaCuadrante::create([
            'cuadrante_mes_id' => $cuadrante->id,
            'usuario_id' => $usuario->id,
            'centro_id' => $centro->id,
            'fecha' => '2026-06-10',
            'franjas' => [['inicio' => '09:00', 'fin' => '14:00']],
            'anulada' => false,
        ]);
    }

    private function crearCuadrantePublicado(Centro $centro): CuadranteMes
    {
        return CuadranteMes::create([
            'centro_id' => $centro->id,
            'anyo' => 2026,
            'mes' => 6,
            'estado' => EstadoCuadrante::Publicado->value,
            'generado_con_ia' => false,
            'generado_automaticamente' => false,
            'publicado_en' => now(),
        ]);
    }

    // =========================================================================
    // PF-08.1 — Crear un evento bloquea los slots disponibles de la franja
    // =========================================================================

    #[Test]
    public function test_pf_08_1_crear_evento_bloquea_slots_disponibles(): void
    {
        $supervisor = User::factory()->create();
        $profesional = User::factory()->create();
        $centro = $this->crearCentro('Centro PF-08.1');
        $horario = $this->crearHorario($centro);
        $tipoSlot = $this->crearTipoSlot($horario);
        $cuadrante = $this->crearCuadrantePublicado($centro);
        $linea = $this->crearLineaCuadrante($cuadrante, $profesional, $centro);

        // Dos slots en la franja del evento (10:00-11:00)
        $slot1 = $this->crearSlotDisponible($profesional, $centro, $tipoSlot, $linea, '10:00', '10:30');
        $slot2 = $this->crearSlotDisponible($profesional, $centro, $tipoSlot, $linea, '10:30', '11:00');
        // Un slot fuera de la franja
        $slot3 = $this->crearSlotDisponible($profesional, $centro, $tipoSlot, $linea, '11:00', '11:30');

        $evento = EventoAgenda::create([
            'centro_id' => $centro->id,
            'tipo_evento' => 'reunion_equipo',
            'titulo' => 'Reunión de equipo',
            'fecha' => '2026-06-10',
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'creado_por_id' => $supervisor->id,
        ]);

        $conflictos = $evento->agregarProfesionales([$profesional->id]);

        $this->assertEquals(EstadoSlot::BloqueadoEvento, $slot1->fresh()->estado);
        $this->assertEquals(EstadoSlot::BloqueadoEvento, $slot2->fresh()->estado);
        $this->assertEquals(EstadoSlot::Disponible, $slot3->fresh()->estado, 'El slot fuera de la franja no se bloquea');
        $this->assertEmpty($conflictos, 'Sin citas confirmadas no debe haber conflictos');

        $this->assertTrue(
            $evento->profesionales()->where('users.id', $profesional->id)->exists(),
            'El profesional queda convocado al evento'
        );
    }

    // =========================================================================
    // PF-08.2 — Evento sobre una cita confirmada genera aviso pero no bloquea la cita
    // =========================================================================

    #[Test]
    public function test_pf_08_2_evento_sobre_cita_confirmada_genera_aviso(): void
    {
        $supervisor = User::factory()->create();
        $profesional = User::factory()->create();
        $centro = $this->crearCentro('Centro PF-08.2');
        $horario = $this->crearHorario($centro);
        $tipoSlot = $this->crearTipoSlot($horario);
        $cuadrante = $this->crearCuadrantePublicado($centro);
        $linea = $this->crearLineaCuadrante($cuadrante, $profesional, $centro);

        // Slot en la franja con cita confirmada → está reservado
        $slotReservado = Slot::create([
            'linea_cuadrante_id' => $linea->id,
            'usuario_id' => $profesional->id,
            'centro_id' => $centro->id,
            'tipo_slot_id' => $tipoSlot->id,
            'fecha' => '2026-06-10',
            'hora_inicio' => '10:00',
            'hora_fin' => '10:30',
            'estado' => EstadoSlot::Reservado->value,
        ]);

        // La cita confirmada vinculada al slot reservado
        $ciudadano = Ciudadano::factory()->create();
        $cita = Cita::create([
            'slot_id' => $slotReservado->id,
            'ciudadano_id' => $ciudadano->id,
            'profesional_id' => $profesional->id,
            'tipo_slot_id' => $tipoSlot->id,
            'centro_id' => $centro->id,
            'fecha' => '2026-06-10',
            'hora_inicio' => '10:00',
            'hora_fin' => '10:30',
            'estado' => 'confirmada',
            'origen' => 'interno',
        ]);

        $evento = EventoAgenda::create([
            'centro_id' => $centro->id,
            'tipo_evento' => 'reunion_equipo',
            'titulo' => 'Reunión de equipo',
            'fecha' => '2026-06-10',
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'creado_por_id' => $supervisor->id,
        ]);

        $conflictos = $evento->agregarProfesionales([$profesional->id]);

        // El slot reservado no se bloquea (ya tiene cita activa)
        $this->assertEquals(EstadoSlot::Reservado, $slotReservado->fresh()->estado);

        // El sistema avisa del conflicto con la cita
        $this->assertArrayHasKey($profesional->id, $conflictos);
        $this->assertTrue($conflictos[$profesional->id]->contains('id', $cita->id));
    }

    // =========================================================================
    // PF-08.3 — Dos eventos en el mismo espacio y franja generan aviso sin bloquear
    // =========================================================================

    #[Test]
    public function test_pf_08_3_conflicto_espacio_genera_aviso_sin_bloquear(): void
    {
        $supervisor = User::factory()->create();
        $centro = $this->crearCentro('Centro PF-08.3');

        $tipoEspacio = TipoEspacio::create(['nombre' => 'Sala de reuniones', 'activo' => true]);
        $coleccion = ColeccionPlazas::create([
            'centro_id' => $centro->id,
            'nombre' => 'Colección principal',
            'tipo_plaza' => 'pernocta',
            'modo_acceso' => 'prescripcion_directa',
            'capacidad' => 10,
            'activa' => true,
            'fecha_alta' => now()->toDateString(),
        ]);
        $espacio = Espacio::create([
            'coleccion_plazas_id' => $coleccion->id,
            'tipo_espacio_id' => $tipoEspacio->id,
            'nombre' => 'Sala A',
            'capacidad' => 10,
        ]);

        $evento1 = EventoAgenda::create([
            'centro_id' => $centro->id,
            'tipo_evento' => 'formacion',
            'titulo' => 'Formación interna',
            'fecha' => '2026-06-10',
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'espacio_id' => $espacio->id,
            'creado_por_id' => $supervisor->id,
        ]);

        // Segundo evento en el mismo espacio, franja solapada (sin excepción)
        $evento2 = EventoAgenda::create([
            'centro_id' => $centro->id,
            'tipo_evento' => 'reunion_equipo',
            'titulo' => 'Reunión de coordinación',
            'fecha' => '2026-06-10',
            'hora_inicio' => '10:30',
            'hora_fin' => '11:30',
            'espacio_id' => $espacio->id,
            'creado_por_id' => $supervisor->id,
        ]);

        // Ambos eventos se crean sin excepción (el sistema no bloquea la creación)
        $this->assertDatabaseHas('eventos_agenda', ['id' => $evento1->id]);
        $this->assertDatabaseHas('eventos_agenda', ['id' => $evento2->id]);

        // Ambos eventos detectan el solapamiento entre sí → el supervisor puede gestionarlo
        $this->assertTrue($evento1->detectarConflictoEspacio(), 'El primer evento detecta conflicto con el segundo');
        $this->assertTrue($evento2->detectarConflictoEspacio(), 'El segundo evento detecta conflicto con el primero');
    }

    // =========================================================================
    // PF-08.4 — En modo básico el evento simplificado bloquea la franja sin gestión de espacios
    // =========================================================================

    #[Test]
    public function test_pf_08_4_modo_basico_evento_sin_espacio(): void
    {
        $supervisor = User::factory()->create();
        $profesional = User::factory()->create();
        $centro = $this->crearCentro('Centro PF-08.4 Básico', 'basico');
        $horario = $this->crearHorario($centro, 'basico');
        $tipoSlot = $this->crearTipoSlot($horario);
        $cuadrante = $this->crearCuadrantePublicado($centro);
        $linea = $this->crearLineaCuadrante($cuadrante, $profesional, $centro);

        $slotDisponible = $this->crearSlotDisponible($profesional, $centro, $tipoSlot, $linea, '10:00', '11:00');

        // En modo básico: evento simplificado sin espacio ni convocatoria formal
        $evento = EventoAgenda::create([
            'centro_id' => $centro->id,
            'tipo_evento' => 'bloqueo_franja',
            'titulo' => 'Reunión de equipo (básico)',
            'fecha' => '2026-06-10',
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'espacio_id' => null,
            'creado_por_id' => $supervisor->id,
        ]);

        $evento->agregarProfesionales([$profesional->id]);

        // El slot queda bloqueado igualmente en modo básico
        $this->assertEquals(EstadoSlot::BloqueadoEvento, $slotDisponible->fresh()->estado);

        // No se requiere espacio en modo básico
        $this->assertNull($evento->espacio_id);
    }
}
