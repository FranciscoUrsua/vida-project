<?php

namespace Modules\Intervencion\Tests\Feature\Encargo\Actuaciones;

use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Escalas\Models\PaseEscala;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use Modules\Intervencion\Http\Livewire\EncargoPage;
use Modules\Intervencion\Http\Livewire\EscalaEncargoPage;
use Modules\Intervencion\Http\Livewire\RegistrarValoracionPage;
use Modules\Intervencion\Http\Livewire\ValoracionEncargoPage;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Intervencion\Services\Actuaciones\AnotacionService;
use Modules\Intervencion\Services\Actuaciones\ContextoActuacion;
use Modules\Intervencion\Services\Actuaciones\PaseEscalaService;
use Modules\Intervencion\Services\Actuaciones\ValoracionService;
use Modules\Intervencion\Services\ActuacionesEncargo;
use Modules\Intervencion\Services\EncargoService;
use Modules\Intervencion\Tests\Concerns\ActuacionesEncargoTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo D — Fichas y escalas (TF-EAC-17 a 24, docs/instrucciones-cli/2026-11-encargo-actuaciones.md).
 *
 * La profesional de encargo registra fichas de valoración y pases de escala desde el encargo,
 * solo con las herramientas del área (EA-2) y sin pasar por la Historia Social. No ve nada de
 * lo registrado por otros en la Historia (EA-7, EA-8): hoy no hay pre-relleno ni serie temporal
 * en la interfaz (decisión del 26 nov), así que se comprueba que las páginas del encargo no
 * muestran fichas ni pases del TSR. Con el encargo cerrado conserva la lectura de lo suyo (EA-11).
 * Los negativos fallan si se quita la restricción que protegen.
 */
class FichasEscalasEncargoTest extends TestCase
{
    use ActuacionesEncargoTestSetup;
    use RefreshDatabase;

    private Derivacion $encargo;

    /**
     * Escenario de las actuaciones con un encargo jurídico en curso de `$ana` para la abogada.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarActuaciones();

        $this->encargo = $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas);
    }

    /** TF-EAC-17 — Ficha desde el encargo: snapshot, vínculo, apunte de valoración y timeline del TSR. */
    #[Test]
    public function tf_eac_17_ficha_desde_el_encargo(): void
    {
        $this->actingAs($this->abogadaVallecas);
        $this->get(route('intervencion.encargos.valoracion', $this->encargo->id))
            ->assertOk()
            ->assertSee('Situación jurídica (test)');

        Livewire::test(ValoracionEncargoPage::class, ['encargo' => $this->encargo->id])
            ->call('seleccionarFicha', $this->fichaJuridica->id)
            ->set('datos.situacion', 'Deuda hipotecaria con demanda')
            ->call('guardarDefinitivo')
            ->assertHasNoErrors()
            ->assertRedirect(route('intervencion.encargos.show', $this->encargo->id));

        $ficha = Ficha::withoutGlobalScopes()->sole();
        // jsonb reordena las claves: se compara con el schema leído de la BD
        $this->assertEquals($this->fichaJuridica->refresh()->schema, $ficha->schema_snapshot);
        $this->assertSame($this->encargo->id, $ficha->derivacion_id);
        $this->assertSame(['situacion' => 'Deuda hipotecaria con demanda'], $ficha->datos);

        $apunte = Apunte::withoutGlobalScopes()->where('tipo', TipoApunte::Valoracion)->sole();
        $this->assertSame($ficha->id, $apunte->apuntable_id);
        $this->assertSame($this->encargo->id, $apunte->derivacion_id);
        $this->assertNull($apunte->plan_id);

        // En su lista del encargo, con enlace a la ficha
        Livewire::test(EncargoPage::class, ['encargo' => $this->encargo->id])
            ->assertSeeHtml(route('intervencion.encargos.ficha', ['encargo' => $this->encargo->id, 'ficha' => $ficha->id]));

        $this->actingAs($this->tsNorte);
        Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna])
            ->assertSee('Situación jurídica (test)')
            ->assertSee('Encargo · Jurídica (test) · '.$this->ciamVallecas->nombre);
    }

    /** TF-EAC-18 — Una ficha que no es del área no se ofrece ni se guarda, tampoco forjada. [negativo] */
    #[Test]
    public function tf_eac_18_ficha_fuera_del_area(): void
    {
        $ajena = TipoFicha::create([
            'nombre' => 'Ficha social general (test)',
            'schema' => ['campos' => [['id' => 'nota', 'tipo' => 'texto', 'etiqueta' => 'Nota', 'descripcion' => null, 'obligatorio' => false, 'orden' => 1]]],
            'activo' => true,
        ]);

        $this->actingAs($this->abogadaVallecas);
        Livewire::test(ValoracionEncargoPage::class, ['encargo' => $this->encargo->id])
            ->assertSee('Situación jurídica (test)')
            ->assertDontSee('Ficha social general (test)')
            ->set('tipoFichaId', $ajena->id)
            ->set('datos', ['nota' => 'Forjada'])
            ->call('guardarDefinitivo')
            ->assertHasErrors('tipoFichaId')
            ->assertNoRedirect();

        try {
            app(ValoracionService::class)->registrar(ContextoActuacion::desdeEncargo($this->encargo, $this->abogadaVallecas), $ajena, ['nota' => 'Forjada']);
            $this->fail('La ficha fuera del área no debía guardarse');
        } catch (DomainException $e) {
            $this->assertStringContainsString('no es del área', $e->getMessage());
        }

        $this->assertSame(0, Ficha::withoutGlobalScopes()->count());
    }

    /** TF-EAC-19 — Sin antecedentes de otros: la ficha del TSR del mismo tipo no se propone ni se ve. [negativo] */
    #[Test]
    public function tf_eac_19_sin_prerrelleno_con_lo_del_tsr(): void
    {
        $this->actingAs($this->tsNorte);
        $fichaTsr = app(ValoracionService::class)->registrar(
            ContextoActuacion::desdeHistoria($this->historiaAna, $this->tsNorte, $this->pisoAna->id),
            $this->fichaJuridica,
            ['situacion' => 'Valoración del TSR: impago de alquiler'],
            'Notas del TSR',
        );

        $this->actingAs($this->abogadaVallecas);
        Livewire::test(ValoracionEncargoPage::class, ['encargo' => $this->encargo->id])
            ->call('seleccionarFicha', $this->fichaJuridica->id)
            ->assertSet('datos.situacion', null)
            ->assertSet('notas', '')
            ->assertDontSee('Valoración del TSR')
            ->assertDontSee('Notas del TSR');

        Livewire::test(EncargoPage::class, ['encargo' => $this->encargo->id])
            ->assertDontSee('Situación jurídica (test)')
            ->assertDontSeeHtml(route('intervencion.encargos.ficha', ['encargo' => $this->encargo->id, 'ficha' => $fichaTsr->id]));

        // Ni por la ruta del encargo con el id de la ficha del TSR
        $this->get(route('intervencion.encargos.ficha', ['encargo' => $this->encargo->id, 'ficha' => $fichaTsr->id]))
            ->assertNotFound();
        $this->assertFalse(Gate::forUser($this->abogadaVallecas)->allows('view', $fichaTsr));
    }

    /** TF-EAC-20 — Pase de escala desde el encargo: puntuación, vínculo y apunte de escala. */
    #[Test]
    public function tf_eac_20_escala_desde_el_encargo(): void
    {
        $this->areaJuridica->tiposEscala()->attach($this->escalaPsicologica);

        $this->actingAs($this->abogadaVallecas);
        $this->get(route('intervencion.encargos.escala', $this->encargo->id))
            ->assertOk()
            ->assertSee('Escala psicológica (test)');

        Livewire::test(EscalaEncargoPage::class, ['encargo' => $this->encargo->id])
            ->set('tipoEscalaId', $this->escalaPsicologica->id)
            ->assertSee('Ítem')
            ->set('respuestas', ['item_1' => 1])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('intervencion.encargos.show', $this->encargo->id));

        $pase = PaseEscala::sole();
        $this->assertSame(1, $pase->score_total);
        $this->assertSame($this->encargo->id, $pase->derivacion_id);

        $apunte = Apunte::withoutGlobalScopes()->where('tipo', TipoApunte::Escala)->sole();
        $this->assertSame($pase->id, $apunte->apuntable_id);
        $this->assertSame($this->encargo->id, $apunte->derivacion_id);
        $this->assertNull($apunte->plan_id);

        Livewire::test(EncargoPage::class, ['encargo' => $this->encargo->id])
            ->assertSee('Escala psicológica (test): puntuación 1');
    }

    /** TF-EAC-21 — Una escala que no es del área no se ofrece ni se guarda, tampoco forjada. [negativo] */
    #[Test]
    public function tf_eac_21_escala_fuera_del_area(): void
    {
        // La escala psicológica es del área psicológica, no de la jurídica del encargo
        $this->actingAs($this->abogadaVallecas);
        Livewire::test(EscalaEncargoPage::class, ['encargo' => $this->encargo->id])
            ->assertDontSee('Escala psicológica (test)')
            ->set('tipoEscalaId', $this->escalaPsicologica->id)
            ->set('respuestas', ['item_1' => 1])
            ->call('guardar')
            ->assertHasErrors('tipoEscalaId')
            ->assertNoRedirect();

        try {
            app(PaseEscalaService::class)->registrar(ContextoActuacion::desdeEncargo($this->encargo, $this->abogadaVallecas), $this->escalaPsicologica, ['item_1' => 1]);
            $this->fail('La escala fuera del área no debía guardarse');
        } catch (DomainException $e) {
            $this->assertStringContainsString('no es del área', $e->getMessage());
        }

        $this->assertSame(0, PaseEscala::count());
    }

    /** TF-EAC-22 — Sin serie con lo de otros: la abogada solo ve sus pases, no los del TSR con la misma escala. [negativo] */
    #[Test]
    public function tf_eac_22_solo_sus_pases(): void
    {
        $this->areaJuridica->tiposEscala()->attach($this->escalaPsicologica);

        $this->actingAs($this->tsNorte);
        $paseTsr = app(PaseEscalaService::class)->registrar(
            ContextoActuacion::desdeHistoria($this->historiaAna, $this->tsNorte, $this->pisoAna->id),
            $this->escalaPsicologica,
            ['item_1' => 1],
        );

        $this->actingAs($this->abogadaVallecas);
        app(PaseEscalaService::class)->registrar(ContextoActuacion::desdeEncargo($this->encargo, $this->abogadaVallecas), $this->escalaPsicologica, ['item_1' => 0]);

        Livewire::test(EncargoPage::class, ['encargo' => $this->encargo->id])
            ->assertSee('Escala psicológica (test): puntuación 0')
            ->assertDontSee('Escala psicológica (test): puntuación 1');

        Livewire::test(EscalaEncargoPage::class, ['encargo' => $this->encargo->id])
            ->set('tipoEscalaId', $this->escalaPsicologica->id)
            ->assertDontSee('puntuación 1');

        $visibles = app(ActuacionesEncargo::class)->visibles($this->abogadaVallecas, $this->encargo);
        $this->assertSame([TipoApunte::Escala], $visibles->pluck('tipo')->all());
        $this->assertFalse(Gate::forUser($this->abogadaVallecas)->allows('view', $paseTsr));
    }

    /** TF-EAC-23 — Las rutas de la Historia (valoración, escala y ficha) siguen respondiendo 403 a la abogada. [negativo] */
    #[Test]
    public function tf_eac_23_rutas_de_la_historia_cerradas(): void
    {
        $this->actingAs($this->abogadaVallecas);
        $ficha = app(ValoracionService::class)->registrar(ContextoActuacion::desdeEncargo($this->encargo, $this->abogadaVallecas), $this->fichaJuridica, ['situacion' => 'Suya']);

        $this->get(route('intervencion.valoracion.nueva', $this->historiaAna->id))->assertForbidden();
        $this->get(route('intervencion.escala.nueva', $this->historiaAna->id))->assertForbidden();
        $this->get(route('intervencion.ficha.show', ['historia' => $this->historiaAna->id, 'ficha' => $ficha->id]))->assertForbidden();

        // El componente de la Historia, aunque se monte directamente, tampoco le deja entrar
        Livewire::test(RegistrarValoracionPage::class, ['historia' => $this->historiaAna])
            ->assertForbidden();
    }

    /** TF-EAC-24 — Con el encargo cerrado, ficha y pase propios en lectura; ya no se registra nada. */
    #[Test]
    public function tf_eac_24_lectura_con_el_encargo_cerrado(): void
    {
        $this->areaJuridica->tiposEscala()->attach($this->escalaPsicologica);
        $this->actingAs($this->abogadaVallecas);
        $desdeEncargo = ContextoActuacion::desdeEncargo($this->encargo, $this->abogadaVallecas);
        $ficha = app(ValoracionService::class)->registrar($desdeEncargo, $this->fichaJuridica, ['situacion' => 'Lanzamiento suspendido'], 'Seguir el recurso');
        app(PaseEscalaService::class)->registrar($desdeEncargo, $this->escalaPsicologica, ['item_1' => 1]);
        app(AnotacionService::class)->registrar($desdeEncargo, 'Nota privada del encargo', VisibilidadApunte::Privada);

        app(EncargoService::class)->cerrar($this->encargo, 'Recurso presentado.', $this->abogadaVallecas);

        $urlFicha = route('intervencion.encargos.ficha', ['encargo' => $this->encargo->id, 'ficha' => $ficha->id]);
        $this->get($urlFicha)
            ->assertOk()
            ->assertSee('Lanzamiento suspendido')
            ->assertSee('Seguir el recurso');

        Livewire::test(EncargoPage::class, ['encargo' => $this->encargo->id])
            ->assertSeeHtml($urlFicha)
            ->assertSee('Escala psicológica (test): puntuación 1')
            ->assertSee('Nota privada del encargo')
            ->assertDontSeeHtml(route('intervencion.encargos.valoracion', $this->encargo->id))
            ->assertDontSeeHtml(route('intervencion.encargos.escala', $this->encargo->id));

        $this->get(route('intervencion.encargos.valoracion', $this->encargo->id))->assertForbidden();
        $this->get(route('intervencion.encargos.escala', $this->encargo->id))->assertForbidden();

        // Otro profesional de encargo no abre la ficha por la ruta del encargo
        $this->actingAs($this->educadorMulti)->get($urlFicha)->assertForbidden();
    }
}
