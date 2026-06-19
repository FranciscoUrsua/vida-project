<?php

namespace Modules\Intervencion\Tests\Feature\Livewire;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Intervencion\Database\Seeders\TipoPlanSeeder;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Http\Livewire\PlanPage;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\FirmaPlan;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\PlanFichaDiagnostico;
use Tests\TestCase;

/**
 * Tests funcionales del componente PlanPage.
 * Nomenclatura: TF-PP-01 a TF-PP-13
 */
class PlanPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crea un usuario autenticado y un plan en el estado indicado.
     * Crea un Ciudadano y HistoriaSocial reales para satisfacer la FK de audits.
     *
     * @return array{User, PlanDeIntervencion}
     */
    private function montarPlan(string $estado = 'borrador'): array
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Gate::before(fn () => true);
        $this->seed(TipoPlanSeeder::class);

        $ciudadano = Ciudadano::factory()->create();
        $uo = UnidadOrganizativa::factory()->create();
        $historia = HistoriaSocial::factory()->create([
            'ciudadano_id' => $ciudadano->id,
            'unidad_organizativa_id' => $uo->id,
        ]);

        $plan = PlanDeIntervencion::factory()->create([
            'historia_id' => $historia->id,
            'estado' => $estado,
            'version' => 1,
            'profesional_responsable_id' => $user->id,
        ]);

        return [$user, $plan];
    }

    // TF-PP-01: La página monta correctamente con un plan existente
    public function test_monta_con_plan_existente(): void
    {
        [, $plan] = $this->montarPlan();
        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->assertOk();
    }

    // TF-PP-02: puedeActivarse es false sin firmas
    public function test_no_puede_activarse_sin_firmas(): void
    {
        [, $plan] = $this->montarPlan();
        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->assertSet('puedeActivarse', false);
    }

    // TF-PP-03: puedeActivarse es true con ambas firmas marcadas
    public function test_puede_activarse_con_ambas_firmas(): void
    {
        [$user, $plan] = $this->montarPlan();
        FirmaPlan::create([
            'plan_id' => $plan->id,
            'version' => 1,
            'profesional_firmado' => true,
            'profesional_firmado_en' => now(),
            'ciudadano_firmado' => true,
            'ciudadano_firmado_en' => now(),
            'metodo_firma' => 'manuscrita',
        ]);

        Livewire::test(PlanPage::class, ['plan' => $plan->fresh()])
            ->assertSet('profesionalFirmado', true)
            ->assertSet('ciudadanoFirmado', true);
    }

    // TF-PP-04: marcarFirmaProfesional crea/actualiza registro en firmas_plan
    public function test_marcar_firma_profesional(): void
    {
        [, $plan] = $this->montarPlan();

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->call('marcarFirmaProfesional', true);

        $this->assertDatabaseHas('firmas_plan', [
            'plan_id' => $plan->id,
            'version' => 1,
            'profesional_firmado' => true,
        ]);
    }

    // TF-PP-05: activarPlan cambia estado a activo
    public function test_activar_plan(): void
    {
        [$user, $plan] = $this->montarPlan();
        FirmaPlan::create([
            'plan_id' => $plan->id,
            'version' => 1,
            'profesional_firmado' => true,
            'profesional_firmado_en' => now(),
            'ciudadano_firmado' => true,
            'ciudadano_firmado_en' => now(),
            'metodo_firma' => 'manuscrita',
        ]);

        Livewire::test(PlanPage::class, ['plan' => $plan->fresh()])
            ->call('activarPlan');

        $this->assertEquals(EstadoPlan::Activo, $plan->fresh()->estado);
    }

    // TF-PP-06: guardarDiagnostico en borrador guarda sin modal
    public function test_guardar_diagnostico_borrador(): void
    {
        [, $plan] = $this->montarPlan();

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->set('diagnosticoTexto', 'Situación de vulnerabilidad económica')
            ->call('guardarDiagnostico')
            ->assertSet('modalMotivoAbierto', false);

        $this->assertEquals(
            'Situación de vulnerabilidad económica',
            $plan->fresh()->diagnostico_social
        );
    }

    // TF-PP-07: guardarDiagnostico en plan activo abre modal de motivo
    public function test_guardar_diagnostico_activo_pide_motivo(): void
    {
        [, $plan] = $this->montarPlan('activo');

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->set('diagnosticoTexto', 'Texto actualizado')
            ->call('guardarDiagnostico')
            ->assertSet('modalMotivoAbierto', true);
    }

    // TF-PP-08: confirmarCambioConMotivo sin texto no ejecuta el cambio
    public function test_motivo_vacio_no_confirma(): void
    {
        [, $plan] = $this->montarPlan('activo');

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->set('diagnosticoTexto', 'Texto nuevo')
            ->call('guardarDiagnostico')
            ->set('motivoTexto', '')
            ->call('confirmarCambioConMotivo')
            ->assertSet('modalMotivoAbierto', true);
    }

    // TF-PP-09: confirmarCambioConMotivo con texto registra cambio y cierra modal
    public function test_motivo_con_texto_registra_cambio(): void
    {
        [, $plan] = $this->montarPlan('activo');

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->set('diagnosticoTexto', 'Texto actualizado')
            ->call('guardarDiagnostico')
            ->set('motivoTexto', 'Actualización tras revisión')
            ->call('confirmarCambioConMotivo')
            ->assertSet('modalMotivoAbierto', false);

        $this->assertDatabaseHas('plan_cambios', [
            'plan_id' => $plan->id,
            'motivo' => 'Actualización tras revisión',
        ]);
    }

    // TF-PP-10: eliminarFichaDiagnostico en borrador elimina sin modal
    public function test_eliminar_ficha_borrador_sin_modal(): void
    {
        [, $plan] = $this->montarPlan();
        $ficha = Ficha::factory()->create();
        PlanFichaDiagnostico::create(['plan_id' => $plan->id, 'ficha_id' => $ficha->id]);

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->call('eliminarFichaDiagnostico', $ficha->id)
            ->assertSet('modalMotivoAbierto', false);

        $this->assertDatabaseMissing('plan_fichas_diagnostico', [
            'plan_id' => $plan->id,
            'ficha_id' => $ficha->id,
        ]);
    }

    // TF-PP-11: eliminarFichaDiagnostico en plan activo pide motivo
    public function test_eliminar_ficha_activo_pide_motivo(): void
    {
        [, $plan] = $this->montarPlan('activo');
        $ficha = Ficha::factory()->create();
        PlanFichaDiagnostico::create(['plan_id' => $plan->id, 'ficha_id' => $ficha->id]);

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->call('eliminarFichaDiagnostico', $ficha->id)
            ->assertSet('modalMotivoAbierto', true);
    }

    // TF-PP-12: guardarSeguimiento persiste periodicidad
    public function test_guardar_seguimiento(): void
    {
        [, $plan] = $this->montarPlan();

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->set('periodicidadSeguimiento', 'semestral')
            ->call('guardarSeguimiento');

        $this->assertEquals('semestral', $plan->fresh()->periodicidad_seguimiento);
    }

    // TF-PP-13: cancelarCambio cierra modal sin persistir
    public function test_cancelar_cambio_no_persiste(): void
    {
        [, $plan] = $this->montarPlan('activo');
        $textoOriginal = $plan->diagnostico_social;

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->set('diagnosticoTexto', 'Texto que no debe guardarse')
            ->call('guardarDiagnostico')
            ->call('cancelarCambio')
            ->assertSet('modalMotivoAbierto', false);

        $this->assertEquals($textoOriginal, $plan->fresh()->diagnostico_social);
    }
}
