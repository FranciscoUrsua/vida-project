<?php

namespace Modules\Agenda\Tests\Feature\Supervisor;

use App\Models\Ciudadano;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Livewire\Supervisor\AusenciasSupervisorPage;
use Modules\Agenda\Livewire\Supervisor\Partials\ReasignacionPanel;
use Modules\Agenda\Livewire\Supervisor\Sidebar;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\ExcepcionProfesional;
use Modules\Agenda\Models\ReasignacionCita;
use Modules\Agenda\Models\Slot;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales TF-AGS-10 a TF-AGS-18 — Ausencias y reasignación.
 */
class AusenciasSupervisorTest extends TestCase
{
    use RefreshDatabase;
    use AgendaSupervisorTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);
        $this->construirFixturesSupervisor();
    }

    // -------------------------------------------------------------------------
    // Helpers internos
    // -------------------------------------------------------------------------

    private function crearCiudadano(): Ciudadano
    {
        return Ciudadano::create([
            'nombre'           => 'Ciudadana',
            'apellido1'        => 'Test',
            'sexo'             => 'F',
            'fecha_nacimiento' => '1985-06-15',
        ]);
    }

    private function crearExcepcionHoy(): ExcepcionProfesional
    {
        return ExcepcionProfesional::create([
            'usuario_id'            => $this->profesional1->id,
            'centro_id'             => $this->centro->id,
            'tipo'                  => 'baja_medica',
            'fecha_inicio'          => now()->toDateString(),
            'fecha_fin'             => now()->toDateString(),
            'afecta_disponibilidad' => true,
            'origen'                => 'manual',
            'creado_por_id'         => $this->supervisor->id,
        ]);
    }

    private function crearCitaCanceladaAusencia(int $ciudadanoId): Cita
    {
        $tipoSlot = $this->crearTipoSlot();

        return Cita::create([
            'slot_id'           => null,
            'ciudadano_id'      => $ciudadanoId,
            'profesional_id'    => $this->profesional1->id,
            'tipo_slot_id'      => $tipoSlot->id,
            'centro_id'         => $this->centro->id,
            'fecha'             => now()->toDateString(),
            'hora_inicio'       => '10:00',
            'hora_fin'          => '10:30',
            'estado'            => EstadoCita::Cancelada->value,
            'motivo_cancelacion'=> 'Ausencia del profesional',
            'origen'            => 'interno',
        ]);
    }

    private function crearSlotUrgencia(): Slot
    {
        $tipoSlot = $this->crearTipoSlot();
        $linea    = $this->crearLineaCuadrante($this->profesional2);

        return Slot::create([
            'linea_cuadrante_id' => $linea->id,
            'usuario_id'         => $this->profesional2->id,
            'centro_id'          => $this->centro->id,
            'tipo_slot_id'       => $tipoSlot->id,
            'fecha'              => now()->toDateString(),
            'hora_inicio'        => '10:00',
            'hora_fin'           => '10:30',
            'estado'             => EstadoSlot::BloqueadoUrgencia->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * TF-AGS-10 — Profesional con ausencia sobrevenida aparece en el panel de ausencias.
     */
    #[Test]
    public function profesional_con_ausencia_aparece_en_panel(): void
    {
        $this->crearExcepcionHoy();
        $ciudadano = $this->crearCiudadano();
        $this->crearCitaCanceladaAusencia($ciudadano->id);
        $this->crearCitaCanceladaAusencia($ciudadano->id);
        $this->crearCitaCanceladaAusencia($ciudadano->id);

        $component = Livewire::actingAs($this->supervisor)
            ->test(AusenciasSupervisorPage::class);

        $this->assertCount(1, $component->get('ausenciasHoy'));
        $this->assertCount(3, $component->get('citasPendientes'));
    }

    /**
     * TF-AGS-11 — El badge del sidebar refleja el número de citas pendientes.
     */
    #[Test]
    public function badge_sidebar_refleja_citas_pendientes(): void
    {
        $ciudadano = $this->crearCiudadano();
        $this->crearCitaCanceladaAusencia($ciudadano->id);
        $this->crearCitaCanceladaAusencia($ciudadano->id);
        $this->crearCitaCanceladaAusencia($ciudadano->id);

        $sidebar = Livewire::actingAs($this->supervisor)
            ->test(Sidebar::class);

        $this->assertEquals(3, $sidebar->get('citasPendientesBadge'));
    }

    /**
     * TF-AGS-12 — El panel de reasignación muestra los slots de urgencia primero.
     */
    #[Test]
    public function panel_reasignacion_muestra_urgencias_primero(): void
    {
        $ciudadano = $this->crearCiudadano();
        $cita      = $this->crearCitaCanceladaAusencia($ciudadano->id);

        // Dos urgencias de profesional2 y profesional3
        $tipoSlot = $this->crearTipoSlot();
        $linea2   = $this->crearLineaCuadrante($this->profesional2);
        $linea3   = $this->crearLineaCuadrante($this->profesional3);

        $urg2 = Slot::create(['linea_cuadrante_id' => $linea2->id, 'usuario_id' => $this->profesional2->id, 'centro_id' => $this->centro->id, 'tipo_slot_id' => $tipoSlot->id, 'fecha' => now()->toDateString(), 'hora_inicio' => '10:00', 'hora_fin' => '10:30', 'estado' => EstadoSlot::BloqueadoUrgencia->value]);
        $urg3 = Slot::create(['linea_cuadrante_id' => $linea3->id, 'usuario_id' => $this->profesional3->id, 'centro_id' => $this->centro->id, 'tipo_slot_id' => $tipoSlot->id, 'fecha' => now()->toDateString(), 'hora_inicio' => '10:00', 'hora_fin' => '10:30', 'estado' => EstadoSlot::BloqueadoUrgencia->value]);

        // Un slot ordinario
        $ord = Slot::create(['linea_cuadrante_id' => $linea2->id, 'usuario_id' => $this->profesional2->id, 'centro_id' => $this->centro->id, 'tipo_slot_id' => $tipoSlot->id, 'fecha' => now()->toDateString(), 'hora_inicio' => '11:00', 'hora_fin' => '11:30', 'estado' => EstadoSlot::Disponible->value]);

        // Actualizar la cita con el tipo_slot_id correcto
        $cita->update(['tipo_slot_id' => $tipoSlot->id]);

        $panel = Livewire::actingAs($this->supervisor)
            ->test(ReasignacionPanel::class, ['citaId' => $cita->id]);

        $slots = $panel->get('slotsDisponiblesHoy');

        $this->assertCount(3, $slots);
        // Los primeros 2 deben ser urgencias
        $this->assertEquals(EstadoSlot::BloqueadoUrgencia, $slots->first()->estado);
    }

    /**
     * TF-AGS-13 — Reasignar una cita crea ReasignacionCita y actualiza la cita y el slot.
     */
    #[Test]
    public function reasignar_cita_crea_registro_y_actualiza_estado(): void
    {
        $ciudadano = $this->crearCiudadano();
        $cita      = $this->crearCitaCanceladaAusencia($ciudadano->id);
        $slotUrg   = $this->crearSlotUrgencia();

        $cita->update(['tipo_slot_id' => $slotUrg->tipo_slot_id]);

        Livewire::actingAs($this->supervisor)
            ->test(ReasignacionPanel::class, ['citaId' => $cita->id])
            ->call('confirmarReasignacion', $slotUrg->id);

        $this->assertDatabaseHas('reasignaciones_cita', [
            'cita_id'             => $cita->id,
            'slot_nuevo_id'       => $slotUrg->id,
            'profesional_nuevo_id'=> $this->profesional2->id,
        ]);

        $this->assertEquals(EstadoCita::Confirmada, $cita->fresh()->estado);
        $this->assertEquals(EstadoSlot::Reservado, $slotUrg->fresh()->estado);
    }

    /**
     * TF-AGS-14 — El badge desaparece cuando todas las citas están gestionadas.
     */
    #[Test]
    public function badge_desaparece_cuando_todas_gestionadas(): void
    {
        $ciudadano = $this->crearCiudadano();
        $cita1 = $this->crearCitaCanceladaAusencia($ciudadano->id);
        $cita2 = $this->crearCitaCanceladaAusencia($ciudadano->id);

        // Descartar la cita2
        Livewire::actingAs($this->supervisor)
            ->test(AusenciasSupervisorPage::class)
            ->call('descartar', $cita2->id);

        // Reasignar la cita1
        $slotUrg = $this->crearSlotUrgencia();
        $cita1->update(['tipo_slot_id' => $slotUrg->tipo_slot_id]);

        Livewire::actingAs($this->supervisor)
            ->test(ReasignacionPanel::class, ['citaId' => $cita1->id])
            ->call('confirmarReasignacion', $slotUrg->id);

        $sidebar = Livewire::actingAs($this->supervisor)
            ->test(Sidebar::class);

        $this->assertEquals(0, $sidebar->get('citasPendientesBadge'));
    }

    /**
     * TF-AGS-15 — Descartar una cita la deja cancelada con el motivo correcto.
     */
    #[Test]
    public function descartar_cita_actualiza_motivo_sin_reasignacion(): void
    {
        $ciudadano = $this->crearCiudadano();
        $cita      = $this->crearCitaCanceladaAusencia($ciudadano->id);

        Livewire::actingAs($this->supervisor)
            ->test(AusenciasSupervisorPage::class)
            ->call('descartar', $cita->id);

        $citaActualizada = $cita->fresh();
        $this->assertStringContainsString('descartada', $citaActualizada->motivo_cancelacion);
        $this->assertEquals(EstadoCita::Cancelada, $citaActualizada->estado);
        $this->assertNull(ReasignacionCita::where('cita_id', $cita->id)->first());
    }

    /**
     * TF-AGS-16 — Si no hay slots disponibles hoy, el panel lo indica.
     */
    #[Test]
    public function panel_indica_cuando_no_hay_slots_disponibles(): void
    {
        $ciudadano = $this->crearCiudadano();
        $cita      = $this->crearCitaCanceladaAusencia($ciudadano->id);

        $panel = Livewire::actingAs($this->supervisor)
            ->test(ReasignacionPanel::class, ['citaId' => $cita->id]);

        $this->assertCount(0, $panel->get('slotsDisponiblesHoy'));
        $panel->assertSee('No hay slots disponibles');
    }

    /**
     * TF-AGS-17 — Un no-show de ciudadano aparece en su sección y no en ausencias sobrevenidas.
     */
    #[Test]
    public function noshow_ciudadano_aparece_en_seccion_correcta(): void
    {
        $tipoSlot  = $this->crearTipoSlot();
        $ciudadano = $this->crearCiudadano();

        Cita::create([
            'slot_id'       => null,
            'ciudadano_id'  => $ciudadano->id,
            'profesional_id'=> $this->profesional1->id,
            'tipo_slot_id'  => $tipoSlot->id,
            'centro_id'     => $this->centro->id,
            'fecha'         => now()->toDateString(),
            'hora_inicio'   => '10:00',
            'hora_fin'      => '10:30',
            'estado'        => EstadoCita::NoShowCiudadano->value,
            'origen'        => 'interno',
        ]);

        $component = Livewire::actingAs($this->supervisor)
            ->test(AusenciasSupervisorPage::class);

        $this->assertCount(1, $component->get('noshowsCiudadanos'));
        $this->assertCount(0, $component->get('citasPendientes'));
    }

    /**
     * TF-AGS-18 — La pantalla de ausencias no muestra citas de días anteriores.
     */
    #[Test]
    public function ausencias_no_muestra_citas_de_ayer(): void
    {
        $tipoSlot  = $this->crearTipoSlot();
        $ciudadano = $this->crearCiudadano();

        Cita::create([
            'slot_id'           => null,
            'ciudadano_id'      => $ciudadano->id,
            'profesional_id'    => $this->profesional1->id,
            'tipo_slot_id'      => $tipoSlot->id,
            'centro_id'         => $this->centro->id,
            'fecha'             => now()->subDay()->toDateString(),
            'hora_inicio'       => '10:00',
            'hora_fin'          => '10:30',
            'estado'            => EstadoCita::Cancelada->value,
            'motivo_cancelacion'=> 'Ausencia del profesional',
            'origen'            => 'interno',
        ]);

        $component = Livewire::actingAs($this->supervisor)
            ->test(AusenciasSupervisorPage::class);

        $this->assertCount(0, $component->get('citasPendientes'));
    }
}
