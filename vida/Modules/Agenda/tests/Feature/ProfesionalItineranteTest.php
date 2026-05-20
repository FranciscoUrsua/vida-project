<?php

namespace Modules\Agenda\Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Enums\OrigenExcepcion;
use Modules\Agenda\Enums\TipoExcepcion;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\ExcepcionProfesional;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\LineaCuadrante;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Models\TipoSlot;
use Modules\Agenda\Services\DisponibilidadService;
use Modules\Centro\Models\Centro;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfesionalItineranteTest extends TestCase
{
    use RefreshDatabase;

    private function crearCentroConHorario(string $nombre): array
    {
        $centro  = Centro::create([
            'nombre'       => $nombre,
            'tipo_gestion' => 'municipal_directo',
            'fecha_alta'   => now()->toDateString(),
        ]);
        $horario = HorarioCentro::create([
            'centro_id'             => $centro->id,
            'nombre'                => 'Horario ' . $nombre,
            'dias_laborables'       => [1, 2, 3, 4, 5],
            'hora_apertura'         => '08:00',
            'hora_cierre'           => '18:00',
            'hora_inicio_atencion'  => '09:00',
            'hora_fin_atencion'     => '14:00',
            'buffer_inicio_minutos' => 0,
            'buffer_fin_minutos'    => 0,
            'vigente_desde'         => '2026-01-01',
            'vigente_hasta'         => null,
            'modo_agenda'           => 'estandar',
            'activo'                => true,
        ]);
        $tipoSlot = TipoSlot::create([
            'horario_centro_id'        => $horario->id,
            'nombre'                   => 'Cita',
            'duracion_minutos'         => 45,
            'requiere_espacio'         => false,
            'porcentaje_urgencias'     => 0,
            'origen_permitido'         => 'ambos',
            'genera_apunte_automatico' => false,
            'activo'                   => true,
        ]);

        return [$centro, $tipoSlot];
    }

    // =========================================================================
    // PF-09.1 — Itinerante no tiene disponibilidad en el centro que no le corresponde ese día
    // =========================================================================

    #[Test]
    public function test_pf_09_1_itinerante_sin_disponibilidad_en_centro_equivocado(): void
    {
        $usuario           = User::factory()->create();
        [$centroA, $tipoA] = $this->crearCentroConHorario('Centro A');
        [$centroB, $tipoB] = $this->crearCentroConHorario('Centro B');

        $cuadranteA = CuadranteMes::create([
            'centro_id'               => $centroA->id,
            'anyo'                    => 2026,
            'mes'                     => 6,
            'estado'                  => EstadoCuadrante::Publicado->value,
            'generado_con_ia'         => false,
            'generado_automaticamente' => false,
            'publicado_en'            => now(),
        ]);

        $lineaA = LineaCuadrante::create([
            'cuadrante_mes_id' => $cuadranteA->id,
            'usuario_id'       => $usuario->id,
            'centro_id'        => $centroA->id,
            'fecha'            => '2026-06-10',
            'franjas'          => [['inicio' => '09:00', 'fin' => '14:00']],
            'anulada'          => false,
        ]);

        // El itinerante tiene slot solo en centroA para el 2026-06-10
        Slot::create([
            'linea_cuadrante_id' => $lineaA->id,
            'usuario_id'         => $usuario->id,
            'centro_id'          => $centroA->id,
            'tipo_slot_id'       => $tipoA->id,
            'fecha'              => '2026-06-10',
            'hora_inicio'        => '09:00',
            'hora_fin'           => '09:45',
            'estado'             => EstadoSlot::Disponible->value,
        ]);

        $service = new DisponibilidadService();
        $fecha   = Carbon::parse('2026-06-10');

        // Consulta centroA → hay un slot disponible
        $enA = $service->obtenerSlots($usuario->id, $centroA->id, $tipoA->id, $fecha, $fecha);
        $this->assertCount(1, $enA);

        // Consulta centroB → el itinerante no tiene slots aquí ese día
        $enB = $service->obtenerSlots($usuario->id, $centroB->id, $tipoB->id, $fecha, $fecha);
        $this->assertEmpty($enB);
    }

    // =========================================================================
    // PF-09.2 — Excepción de un centro no afecta disponibilidad en el otro
    // =========================================================================

    #[Test]
    public function test_pf_09_2_excepcion_de_un_centro_no_afecta_al_otro(): void
    {
        $usuario           = User::factory()->create();
        $supervisor        = User::factory()->create();
        [$centroA, $tipoA] = $this->crearCentroConHorario('Centro A PF09.2');
        [$centroB, $tipoB] = $this->crearCentroConHorario('Centro B PF09.2');

        $cuadranteA = CuadranteMes::create([
            'centro_id'               => $centroA->id,
            'anyo'                    => 2026,
            'mes'                     => 6,
            'estado'                  => EstadoCuadrante::Publicado->value,
            'generado_con_ia'         => false,
            'generado_automaticamente' => false,
            'publicado_en'            => now(),
        ]);

        $cuadranteB = CuadranteMes::create([
            'centro_id'               => $centroB->id,
            'anyo'                    => 2026,
            'mes'                     => 6,
            'estado'                  => EstadoCuadrante::Publicado->value,
            'generado_con_ia'         => false,
            'generado_automaticamente' => false,
            'publicado_en'            => now(),
        ]);

        $lineaA = LineaCuadrante::create([
            'cuadrante_mes_id' => $cuadranteA->id,
            'usuario_id'       => $usuario->id,
            'centro_id'        => $centroA->id,
            'fecha'            => '2026-06-10',
            'franjas'          => [['inicio' => '09:00', 'fin' => '14:00']],
            'anulada'          => false,
        ]);

        $lineaB = LineaCuadrante::create([
            'cuadrante_mes_id' => $cuadranteB->id,
            'usuario_id'       => $usuario->id,
            'centro_id'        => $centroB->id,
            'fecha'            => '2026-06-10',
            'franjas'          => [['inicio' => '09:00', 'fin' => '14:00']],
            'anulada'          => false,
        ]);

        // El itinerante tiene un slot disponible en cada centro ese día
        $slotA = Slot::create([
            'linea_cuadrante_id' => $lineaA->id,
            'usuario_id'         => $usuario->id,
            'centro_id'          => $centroA->id,
            'tipo_slot_id'       => $tipoA->id,
            'fecha'              => '2026-06-10',
            'hora_inicio'        => '09:00',
            'hora_fin'           => '09:45',
            'estado'             => EstadoSlot::Disponible->value,
        ]);

        $slotB = Slot::create([
            'linea_cuadrante_id' => $lineaB->id,
            'usuario_id'         => $usuario->id,
            'centro_id'          => $centroB->id,
            'tipo_slot_id'       => $tipoB->id,
            'fecha'              => '2026-06-10',
            'hora_inicio'        => '09:00',
            'hora_fin'           => '09:45',
            'estado'             => EstadoSlot::Disponible->value,
        ]);

        // Excepción solo para centroA → el observer anula lineaA y slotA
        ExcepcionProfesional::create([
            'usuario_id'            => $usuario->id,
            'centro_id'             => $centroA->id,
            'tipo'                  => TipoExcepcion::DiaLibre->value,
            'fecha_inicio'          => '2026-06-10',
            'fecha_fin'             => '2026-06-10',
            'afecta_disponibilidad' => true,
            'franja_afectada'       => null,
            'origen'                => OrigenExcepcion::Manual->value,
            'creado_por_id'         => $supervisor->id,
        ]);

        // El slot de centroA queda anulado por el observer
        $this->assertEquals(EstadoSlot::Anulado, $slotA->fresh()->estado);

        // El slot de centroB no se ve afectado
        $this->assertEquals(EstadoSlot::Disponible, $slotB->fresh()->estado);

        // La lineaA está anulada; la lineaB no
        $this->assertTrue($lineaA->fresh()->anulada);
        $this->assertFalse($lineaB->fresh()->anulada);
    }
}
