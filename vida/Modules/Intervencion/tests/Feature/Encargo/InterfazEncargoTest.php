<?php

namespace Modules\Intervencion\Tests\Feature\Encargo;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Intervencion\Enums\EstadoDerivacion;
use Modules\Intervencion\Enums\ModalidadDerivacion;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use Modules\Intervencion\Http\Livewire\DerivacionesPlan;
use Modules\Intervencion\Http\Livewire\EncargosProfesionales;
use Modules\Intervencion\Http\Livewire\PlanPage;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Services\DerivacionService;
use Modules\Intervencion\Services\EncargoService;
use Modules\Intervencion\Tests\Concerns\EncargoTestSetup;
use Modules\Supervision\Http\Livewire\DerivacionesPage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo B — Interfaz del TSR y bandeja del encargo profesional (TF-ENC-14 a 18,
 * docs/instrucciones-cli/2026-11-encargo-profesional.md).
 *
 * El TSR encarga desde la herramienta «Encargo profesional» de la Historia Social o desde
 * el plan principal; la bandeja de derivaciones del centro de destino lo asigna a un
 * profesional de encargos. Los negativos fallan si se quita la restricción que protegen.
 */
class InterfazEncargoTest extends TestCase
{
    use EncargoTestSetup;
    use RefreshDatabase;

    /**
     * Escenario del encargo profesional.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEncargo();
    }

    /** TF-ENC-14 — Herramienta «Encargo profesional» en la Historia Social: crea el encargo y lo lista. */
    #[Test]
    public function tf_enc_14_herramienta_en_la_historia_social(): void
    {
        $informe = $this->documentoEnCarpeta($this->ana, 'Informe médico');
        $this->actingAs($this->tsNorte);

        Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna])
            ->assertSeeHtml("seleccionarHerramienta('encargo')")
            ->call('seleccionarHerramienta', 'encargo')
            ->assertSeeLivewire(EncargosProfesionales::class);

        $componente = Livewire::test(EncargosProfesionales::class, ['historiaId' => $this->historiaAna->id])
            ->assertSee('Sin encargos profesionales.')
            ->call('abrir')
            ->assertSet('modalAbierto', true)
            ->assertSee('Informe médico')
            ->set('centroDestinoId', $this->ciamVallecas->id)
            ->set('areaId', $this->areaJuridica->id)
            ->set('motivo', 'Orientación jurídica sobre el desahucio')
            ->set('documentoIds', [$informe->id])
            ->call('encargar')
            ->assertHasNoErrors()
            ->assertSet('modalAbierto', false);

        $encargo = Derivacion::query()->encargos()->sole();
        $this->assertSame([$this->historiaAna->id, null, $this->ciamVallecas->id], [$encargo->historia_id, $encargo->plan_origen_id, $encargo->centro_destino_id]);
        $componente->assertSee('CIAM Puente de Vallecas')
            ->assertSee(EstadoDerivacion::Pendiente->label())
            ->assertSee('Orientación jurídica sobre el desahucio');

        // El servicio rechaza un duplicado y la pantalla lo muestra como error.
        $componente->call('abrir')
            ->set('centroDestinoId', $this->ciamVallecas->id)
            ->set('areaId', $this->areaJuridica->id)
            ->set('motivo', 'Otra vez')
            ->call('encargar')
            ->assertHasErrors('motivo');
        $this->assertSame(1, Derivacion::count());
    }

    /** TF-ENC-14 bis — Quien no gestiona la historia ve los encargos, pero no puede encargar. [negativo] */
    #[Test]
    public function tf_enc_14_bis_sin_gestion_no_encarga(): void
    {
        $this->encargoPendiente($this->historiaAna, $this->ciamVallecas);
        // La supervisión del centro lee la historia, pero no la gestiona.
        $this->actingAs($this->supNorte);

        $componente = Livewire::test(EncargosProfesionales::class, ['historiaId' => $this->historiaAna->id]);
        $this->assertFalse($componente->get('puedeEncargar'));
        $componente->assertSee('CIAM Puente de Vallecas')
            ->assertDontSeeHtml('wire:click="abrir"')
            ->call('abrir')
            ->assertSet('modalAbierto', false);
    }

    /** TF-ENC-15 — El plan principal ofrece el encargo (también desde un objetivo) y lo lista en su sección. */
    #[Test]
    public function tf_enc_15_encargo_desde_el_plan_principal(): void
    {
        $this->actingAs($this->tsNorte);

        Livewire::test(PlanPage::class, ['plan' => $this->pisoAna])
            ->assertSeeLivewire(EncargosProfesionales::class)
            ->assertSeeHtml("abrir-encargo', { objetivoId: {$this->objetivoAna1->id} }");

        $componente = Livewire::test(EncargosProfesionales::class, ['historiaId' => $this->historiaAna->id, 'planId' => $this->pisoAna->id])
            ->call('abrir', $this->objetivoAna1->id)
            ->assertSet('objetivoId', $this->objetivoAna1->id)
            ->set('centroDestinoId', $this->ciamVallecas->id)
            ->set('areaId', $this->areaJuridica->id)
            ->set('motivo', 'Orientación jurídica')
            ->call('encargar')
            ->assertHasNoErrors();

        $encargo = Derivacion::query()->encargos()->sole();
        $this->assertSame([$this->pisoAna->id, $this->objetivoAna1->id], [$encargo->plan_origen_id, $encargo->plan_objetivo_id]);
        $componente->assertSee('Objetivo: Mejorar la situación de vivienda');

        // La sección de derivaciones del plan no lo pinta como derivación (no tiene tipo de plan),
        // y el objetivo lo muestra como encargo.
        $this->assertSame([], Livewire::test(DerivacionesPlan::class, ['planId' => $this->pisoAna->id])->get('derivaciones')->pluck('id')->all());
        Livewire::test(PlanPage::class, ['plan' => $this->pisoAna])->assertSee('Encargo profesional a CIAM Puente de Vallecas');
    }

    /** TF-ENC-15 bis — Un plan que no es el principal no ofrece encargos. [negativo] */
    #[Test]
    public function tf_enc_15_bis_plan_no_principal(): void
    {
        $psico = $this->planActivo($this->historiaAna, $this->planPsicologico, $this->cssNorte, $this->psicologoNorte);
        $this->actingAs($this->psicologoNorte);

        $componente = Livewire::test(EncargosProfesionales::class, ['historiaId' => $this->historiaAna->id, 'planId' => $psico->id]);
        $this->assertFalse($componente->get('puedeEncargar'));
        Livewire::test(PlanPage::class, ['plan' => $psico])->assertDontSeeLivewire(EncargosProfesionales::class);
    }

    /** TF-ENC-16 — Centros de destino: solo los que tienen algún profesional con `derivacion.registrar_resultado`. [negativo] */
    #[Test]
    public function tf_enc_16_centros_de_destino(): void
    {
        $this->actingAs($this->tsNorte);
        $destinos = Livewire::test(EncargosProfesionales::class, ['historiaId' => $this->historiaAna->id])
            ->get('centrosDestino')->pluck('id')->all();

        $this->assertContains($this->ciamVallecas->id, $destinos);
        $this->assertContains($this->cssNorte->id, $destinos);
        // Usera tiene profesionales de intervención, pero ninguno que reciba encargos.
        $this->assertTrue($this->psicologa->fresh()->adscripcionesVigentes()->where('unidad_organizativa_id', $this->ciamUsera->unidad_organizativa_id)->exists());
        $this->assertNotContains($this->ciamUsera->id, $destinos);
        $this->assertNotContains($this->cssSur->id, $destinos);
    }

    /** TF-ENC-17 — Bandeja: «Encargo profesional»; al aceptar, los profesionales de encargo del centro. [negativo] */
    #[Test]
    public function tf_enc_17_bandeja_del_centro_de_destino(): void
    {
        $encargo = $this->encargoPendiente($this->historiaAna, $this->ciamVallecas);
        $derivacion = app(DerivacionService::class)->derivar($this->pisoAna, $this->derivPia, $this->ciamVallecas, 'Atención especializada', null, $this->tsNorte);

        $this->actingAs($this->directoraVallecas);
        $bandeja = Livewire::test(DerivacionesPage::class);
        $filas = $bandeja->get('pendientes')->keyBy(fn (array $fila) => $fila['derivacion']->id);

        $this->assertSame(ModalidadDerivacion::Encargo->label().' · '.$this->areaJuridica->nombre, $filas[$encargo->id]['tipo']);
        $this->assertSame('CSS Norte', $filas[$encargo->id]['origen']);
        $this->assertSame('PIA', $filas[$derivacion->id]['tipo']);
        $bandeja->assertSee('Encargo profesional');

        // Encargo: solo profesionales de encargo del centro.
        $bandeja->call('prepararAceptacion', $encargo->id);
        $ofrecidos = $bandeja->get('profesionales')->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$this->abogadaVallecas->id, $this->educadorMulti->id], $ofrecidos);
        $this->assertNotContains($this->psicologaVallecas->id, $ofrecidos);
        $bandeja->assertSee('Asignar el encargo');

        // Derivación: los de intervención, no los de encargo.
        $bandeja->call('prepararAceptacion', $derivacion->id);
        $this->assertContains($this->psicologaVallecas->id, $bandeja->get('profesionales')->pluck('id')->all());
        $this->assertNotContains($this->abogadaVallecas->id, $bandeja->get('profesionales')->pluck('id')->all());

        // Asignar el encargo desde la bandeja.
        $bandeja->call('prepararAceptacion', $encargo->id)
            ->set('responsableId', $this->abogadaVallecas->id)
            ->call('aceptar')
            ->assertHasNoErrors()
            ->assertSet('aviso', 'Encargo asignado: el profesional lo tiene en «Mis derivaciones».');
        $encargo->refresh();
        $this->assertSame([EstadoDerivacion::Aceptada, $this->abogadaVallecas->id, null], [$encargo->estado, $encargo->profesional_responsable_id, $encargo->plan_especializado_id]);

        // Un profesional de intervención no recibe encargos aunque se fuerce desde la bandeja.
        $otro = $this->encargoPendiente($this->historiaMarta, $this->ciamVallecas);
        $bandeja->call('prepararAceptacion', $otro->id)
            ->set('responsableId', $this->psicologaVallecas->id)
            ->call('aceptar')
            ->assertHasErrors('responsableId');
        $this->assertSame(EstadoDerivacion::Pendiente, $otro->fresh()->estado);
    }

    /** TF-ENC-18 — El TSR ve el resultado del encargo cerrado en la lista y en el timeline. */
    #[Test]
    public function tf_enc_18_resultado_en_la_lista_y_el_timeline(): void
    {
        $encargo = $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas);
        app(EncargoService::class)->cerrar($encargo, 'Se informa de los recursos de defensa.', $this->abogadaVallecas);

        $this->actingAs($this->tsNorte);
        Livewire::test(EncargosProfesionales::class, ['historiaId' => $this->historiaAna->id])
            ->assertSee(EstadoDerivacion::Cerrada->label())
            ->assertSee('Se informa de los recursos de defensa.')
            ->assertSee($this->abogadaVallecas->nombreVisible());

        $apuntes = Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna])->get('apuntesHS');
        $resultado = $apuntes->first(fn ($apunte) => $apunte->tipo === TipoApunte::Encargo);
        $this->assertNotNull($resultado);
        $this->assertStringContainsString('Se informa de los recursos de defensa.', $resultado->contenido);
    }
}
