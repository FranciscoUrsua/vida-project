<?php

namespace Modules\Agenda\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Jobs\SlotExpirationJob;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\LineaCuadrante;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Models\TipoSlot;
use Modules\Agenda\Services\SlotMaterializadorService;
use Modules\Centro\Models\Centro;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SlotsDisponibilidadTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // PF-04.1 — El porcentaje de urgencias se aplica con redondeo hacia abajo
    // =========================================================================

    #[Test]
    public function test_pf_04_1_porcentaje_urgencias_con_redondeo_hacia_abajo(): void
    {
        // Setup: atención 9:00-14:00, buffer_inicio 30min → ventana útil 9:30-14:00 = 270 min
        // TipoSlot: 45 min, 20% urgencias
        // total = floor(270/45) = 6 slots
        // urgencia = floor(6 * 20/100) = floor(1.2) = 1
        // normal = 5

        $centro = Centro::create([
            'nombre'       => 'Centro PF-04.1',
            'tipo_gestion' => 'municipal_directo',
            'fecha_alta'   => now()->toDateString(),
        ]);

        $horario = HorarioCentro::create([
            'centro_id'             => $centro->id,
            'nombre'                => 'Horario estándar',
            'dias_laborables'       => [1, 2, 3, 4, 5],
            'hora_apertura'         => '08:00',
            'hora_cierre'           => '19:00',
            'hora_inicio_atencion'  => '09:00',
            'hora_fin_atencion'     => '14:00',
            'buffer_inicio_minutos' => 30,
            'buffer_fin_minutos'    => 0,
            'vigente_desde'         => '2026-01-01',
            'vigente_hasta'         => null,
            'modo_agenda'           => 'estandar',
            'activo'                => true,
        ]);

        TipoSlot::create([
            'horario_centro_id'        => $horario->id,
            'nombre'                   => 'Entrevista TSR',
            'duracion_minutos'         => 45,
            'requiere_espacio'         => false,
            'porcentaje_urgencias'     => 20,
            'origen_permitido'         => 'ambos',
            'genera_apunte_automatico' => false,
            'activo'                   => true,
        ]);

        $usuario   = User::factory()->create();
        $cuadrante = CuadranteMes::create([
            'centro_id'               => $centro->id,
            'anyo'                    => 2026,
            'mes'                     => 6,
            'estado'                  => EstadoCuadrante::Publicado->value,
            'generado_con_ia'         => false,
            'generado_automaticamente' => false,
            'publicado_en'            => now(),
        ]);

        // 2026-06-01 is a Monday
        LineaCuadrante::create([
            'cuadrante_mes_id' => $cuadrante->id,
            'usuario_id'       => $usuario->id,
            'centro_id'        => $centro->id,
            'fecha'            => '2026-06-01',
            'franjas'          => [['inicio' => '09:00', 'fin' => '14:00']],
            'anulada'          => false,
        ]);

        $creados = (new SlotMaterializadorService())->materializar($cuadrante);

        $slots = $cuadrante->slots;

        $this->assertEquals(6, $creados, 'Deben crearse 6 slots (270 min / 45 min)');
        $this->assertEquals(5, $slots->where('estado', EstadoSlot::Disponible)->count(), '5 slots disponibles');
        $this->assertEquals(1, $slots->where('estado', EstadoSlot::BloqueadoUrgencia)->count(), '1 slot de urgencia (floor de 1.2)');

        // Los 5 slots disponibles van primero; el de urgencia es el último
        $ordenados = $slots->sortBy('hora_inicio');
        $this->assertStringStartsWith('09:30', $ordenados->first()->hora_inicio, 'El primer slot empieza a las 9:30');
        $this->assertStringStartsWith('13:15', $ordenados->last()->hora_inicio, 'El slot de urgencia empieza a las 13:15');
        $this->assertEquals(EstadoSlot::BloqueadoUrgencia, $ordenados->last()->estado);
    }

    // =========================================================================
    // PF-04.2 — Slots de urgencia no aparecen en disponibilidad externa
    // =========================================================================

    #[Test]
    public function test_pf_04_2_urgencias_no_visibles_en_consulta_externa(): void
    {
        $this->markTestIncomplete(
            'PF-04.2: pendiente de implementar DisponibilidadService::obtenerSlots()'
        );
    }

    // =========================================================================
    // PF-04.3 — Crear un evento bloquea los slots disponibles de esa franja
    // =========================================================================

    #[Test]
    public function test_pf_04_3_crear_evento_bloquea_slots_disponibles(): void
    {
        $this->markTestIncomplete(
            'PF-04.3: pendiente de implementar lógica de bloqueo de slots al crear EventoAgenda'
        );
    }

    // =========================================================================
    // PF-04.4 — El job de expiración marca como expirados los slots de urgencia no consumidos
    // =========================================================================

    #[Test]
    public function test_pf_04_4_job_expiracion_marca_urgencias_expiradas(): void
    {
        $slot = Slot::factory()->urgencia()->create([
            'fecha'      => now()->subDay()->toDateString(),
            'hora_inicio' => '10:00',
            'hora_fin'   => '10:45',
        ]);

        // SlotExpirationJob es aún un esqueleto; marcamos como pendiente
        $this->markTestIncomplete(
            'PF-04.4: pendiente de implementar SlotExpirationJob::handle()'
        );

        (new SlotExpirationJob())->handle();

        $this->assertEquals(EstadoSlot::Expirado, $slot->fresh()->estado);
    }

    // =========================================================================
    // PF-04.5 — El job de expiración marca como no_ocupado los slots disponibles no reservados
    // =========================================================================

    #[Test]
    public function test_pf_04_5_job_expiracion_marca_disponibles_no_ocupados(): void
    {
        $slot = Slot::factory()->create([
            'fecha'      => now()->subDay()->toDateString(),
            'hora_inicio' => '10:00',
            'hora_fin'   => '10:45',
            'estado'     => EstadoSlot::Disponible->value,
        ]);

        $this->markTestIncomplete(
            'PF-04.5: pendiente de implementar SlotExpirationJob::handle()'
        );

        (new SlotExpirationJob())->handle();

        $this->assertEquals(EstadoSlot::NoOcupado, $slot->fresh()->estado);
    }
}
