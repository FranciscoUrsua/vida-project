<?php

namespace Modules\Agenda\Tests\Feature;

use App\Models\Ciudadano;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Enums\OrigenCita;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\Slot;
use Modules\Centro\Models\Centro;
use Modules\Intervencion\Models\Apunte;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CitaCicloVidaTest extends TestCase
{
    use RefreshDatabase;

    private function crearCentro(string $nombre = 'Centro de Prueba'): Centro
    {
        return Centro::create([
            'nombre' => $nombre,
            'tipo_gestion' => 'municipal_directo',
            'fecha_alta' => now()->toDateString(),
        ]);
    }

    /** Datos mínimos para crear una Cita sobre un slot dado. */
    private function datosCita(Slot $slot, array $override = []): array
    {
        return array_merge([
            'slot_id' => $slot->id,
            'ciudadano_id' => Ciudadano::factory()->create()->id,
            'profesional_id' => $slot->usuario_id,
            'tipo_slot_id' => $slot->tipo_slot_id,
            'centro_id' => $slot->centro_id,
            'fecha' => $slot->fecha->toDateString(),
            'hora_inicio' => $slot->hora_inicio,
            'hora_fin' => $slot->hora_fin,
            'estado' => EstadoCita::Confirmada->value,
            'origen' => OrigenCita::Interno->value,
        ], $override);
    }

    // =========================================================================
    // PF-05.1 — Crear una cita interna cambia el slot a reservado
    // =========================================================================

    #[Test]
    public function test_pf_05_1_crear_cita_interna_cambia_slot_a_reservado(): void
    {
        $slot = Slot::factory()->create(); // estado = disponible
        $datos = $this->datosCita($slot);

        $cita = Cita::create($datos);

        $this->assertEquals(EstadoCita::Confirmada, $cita->estado);
        $this->assertEquals(
            EstadoSlot::Reservado,
            $slot->fresh()->estado,
            'El slot debe pasar a reservado al crear la cita'
        );
    }

    // =========================================================================
    // PF-05.2 — Cita desde API externa registra la referencia externa
    // =========================================================================

    #[Test]
    public function test_pf_05_2_cita_externa_registra_referencia(): void
    {
        $slot = Slot::factory()->create();
        $datos = $this->datosCita($slot, [
            'origen' => OrigenCita::ApiExterna->value,
            'referencia_externa' => 'REF-EXT-0001',
        ]);

        $cita = Cita::create($datos);

        $this->assertEquals(OrigenCita::ApiExterna, $cita->origen);
        $this->assertEquals('REF-EXT-0001', $cita->referencia_externa);
        $this->assertEquals(EstadoSlot::Reservado, $slot->fresh()->estado);
    }

    // =========================================================================
    // PF-05.3 — No se puede crear una cita sobre un slot ya reservado
    // =========================================================================

    #[Test]
    public function test_pf_05_3_no_cita_sobre_slot_reservado(): void
    {
        // La tabla citas tiene unique constraint sobre slot_id (FK unique).
        // Crear una segunda cita sobre el mismo slot viola esa restricción.
        $slot = Slot::factory()->reservado()->create();

        $ciudadano = Ciudadano::factory()->create();
        $profesional = User::factory()->create();

        // Primera cita existente
        Cita::create([
            'slot_id' => $slot->id,
            'ciudadano_id' => $ciudadano->id,
            'profesional_id' => $slot->usuario_id,
            'tipo_slot_id' => $slot->tipo_slot_id,
            'centro_id' => $slot->centro_id,
            'fecha' => $slot->fecha,
            'hora_inicio' => $slot->hora_inicio,
            'hora_fin' => $slot->hora_fin,
            'estado' => EstadoCita::Confirmada->value,
            'origen' => OrigenCita::Interno->value,
        ]);

        $this->expectException(QueryException::class);

        // Segunda cita sobre el mismo slot: viola la restricción unique de slot_id
        Cita::create([
            'slot_id' => $slot->id,
            'ciudadano_id' => $ciudadano->id,
            'profesional_id' => $slot->usuario_id,
            'tipo_slot_id' => $slot->tipo_slot_id,
            'centro_id' => $slot->centro_id,
            'fecha' => $slot->fecha,
            'hora_inicio' => $slot->hora_inicio,
            'hora_fin' => $slot->hora_fin,
            'estado' => EstadoCita::Confirmada->value,
            'origen' => OrigenCita::Interno->value,
        ]);
    }

    // =========================================================================
    // PF-05.4 — No se puede crear una cita sobre un slot de urgencia desde canal externo
    // =========================================================================

    #[Test]
    public function test_pf_05_4_no_cita_externa_sobre_slot_urgencia(): void
    {
        $slot = Slot::factory()->urgencia()->create();
        $datos = $this->datosCita($slot, ['origen' => OrigenCita::ApiExterna->value]);

        $this->expectException(\LogicException::class);

        Cita::create($datos);
    }

    // =========================================================================
    // PF-05.5 — Marcar cita como completada registra el timestamp
    // =========================================================================

    #[Test]
    public function test_pf_05_5_completar_cita_registra_timestamp(): void
    {
        $cita = Cita::factory()->create(); // confirmada

        $this->assertNull($cita->completada_en, 'Antes de completar no debe haber timestamp');

        $cita->completar();

        $citaFresh = $cita->fresh();
        $this->assertEquals(EstadoCita::Completada, $citaFresh->estado);
        $this->assertNotNull($citaFresh->completada_en, 'Debe registrarse el momento de completado');
    }

    // =========================================================================
    // PF-05.6 — Cancelar una cita activa libera el slot
    // =========================================================================

    #[Test]
    public function test_pf_05_6_cancelar_cita_libera_slot(): void
    {
        // Slot con fecha en el futuro: la hora no ha pasado aún
        $slot = Slot::factory()->create([
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '10:00',
            'hora_fin' => '10:45',
        ]);
        $datos = $this->datosCita($slot);

        // Al crear la cita, el observer marca el slot como reservado
        $cita = Cita::create($datos);
        $this->assertEquals(EstadoSlot::Reservado, $slot->fresh()->estado);

        $supervisor = User::factory()->create();
        $cita->cancelar($supervisor, 'Agenda incompatible');

        $citaFresh = $cita->fresh();
        $this->assertEquals(EstadoCita::Cancelada, $citaFresh->estado);
        $this->assertEquals($supervisor->id, $citaFresh->cancelado_por_id);
        $this->assertEquals('Agenda incompatible', $citaFresh->motivo_cancelacion);
        $this->assertEquals(
            EstadoSlot::Disponible,
            $slot->fresh()->estado,
            'El slot futuro debe volver a disponible tras la cancelación'
        );
    }

    // =========================================================================
    // PF-05.7 — Cancelación retroactiva de cita pasada deja el slot en no_ocupado
    // =========================================================================

    #[Test]
    public function test_pf_05_7_cancelacion_retroactiva_slot_queda_no_ocupado(): void
    {
        // Slot con fecha en el pasado: la hora ya transcurrió
        $slot = Slot::factory()->create([
            'fecha' => now()->subDay()->toDateString(),
            'hora_inicio' => '09:00',
            'hora_fin' => '09:45',
        ]);
        $datos = $this->datosCita($slot, [
            'fecha' => $slot->fecha->toDateString(),
            'hora_inicio' => '09:00',
            'hora_fin' => '09:45',
        ]);

        $cita = Cita::create($datos);
        // Verificamos que el slot quedó como reservado tras crear la cita
        $this->assertEquals(EstadoSlot::Reservado, $slot->fresh()->estado);

        $supervisor = User::factory()->create();
        $cita->cancelar($supervisor, 'Cancelación retroactiva');

        $this->assertEquals(EstadoCita::Cancelada, $cita->fresh()->estado);
        $this->assertEquals(
            EstadoSlot::NoOcupado,
            $slot->fresh()->estado,
            'El slot cuya hora ya pasó debe quedar en no_ocupado, no volver a disponible'
        );
    }

    // =========================================================================
    // PF-05.8 — Cancelación retroactiva informa de apuntes antes de confirmar
    // =========================================================================

    #[Test]
    public function test_pf_05_8_cancelacion_retroactiva_informa_apuntes(): void
    {
        // Cita completada con apunte vinculado en Historia Social
        $cita = Cita::factory()->completada()->create([
            'fecha' => now()->subDays(2)->toDateString(),
        ]);

        // Apunte creado automáticamente al completar la cita (polimórfico vía apuntable)
        $apunte = Apunte::factory()->create([
            'apuntable_type' => Cita::class,
            'apuntable_id' => $cita->id,
        ]);

        // Antes de cancelar: el servicio detecta el apunte asociado
        $this->assertCount(
            1,
            $cita->apuntes,
            'Debe detectarse el apunte vinculado a la cita antes de la cancelación'
        );

        // El supervisor confirma y cancela
        $supervisor = User::factory()->create();
        $cita->cancelar($supervisor, 'Error de registro — cita duplicada');

        $this->assertEquals(EstadoCita::Cancelada, $cita->fresh()->estado);

        // Los apuntes existentes NO se eliminan al cancelar la cita
        $this->assertTrue(
            Apunte::where('id', $apunte->id)->exists(),
            'Los apuntes de Historia Social deben permanecer intactos tras la cancelación'
        );
    }
}
