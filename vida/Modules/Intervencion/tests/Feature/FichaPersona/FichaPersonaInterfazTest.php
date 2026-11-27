<?php

namespace Modules\Intervencion\Tests\Feature\FichaPersona;

use App\Enums\AccionAuditEnum;
use App\Models\Audit;
use App\Models\CatalogoSistema;
use App\Models\Ciudadano;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Ciudadania\Http\Livewire\FichaCiudadanoPage;
use Modules\Intervencion\Http\Livewire\FichasPersona;
use Modules\Intervencion\Http\Livewire\RegistrarFichaPersonaPage;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Intervencion\Services\Actuaciones\FichaPersonaService;
use Modules\Intervencion\Tests\Concerns\CircuitoTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Interfaz de las fichas de la persona en la ficha del ciudadano (TF-SDE-26 a 30, bloque D):
 * tarjeta con la situación vigente, «Registrar»/«Actualizar»/«Continuar borrador», historial,
 * página de registro con «No desea contestar» y pre-relleno, y página de lectura.
 *
 * Datos: `$sinHistoria`, persona sin Historia Social; `$this->marta`, protegida con Historia en
 * el CSS Norte; `$this->tsNorte` (intervención), `$this->supNorte` (supervisión), `$cbNorte`
 * (consulta básica) y `$tramNorte` (tramitación, sin permisos de fichas de la persona).
 *
 * @see docs/instrucciones-cli/2026-11-ficha-sociodemografica.md, bloque D
 */
class FichaPersonaInterfazTest extends TestCase
{
    use CircuitoTestSetup;
    use RefreshDatabase;

    private TipoFicha $tipo;

    private Ciudadano $sinHistoria;

    private User $cbNorte;

    private User $tramNorte;

    /**
     * Circuito, persona sin Historia, consulta básica, tramitación y un tipo de ficha de la persona.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCircuito();

        $this->sinHistoria = Ciudadano::factory()->create();
        $this->cbNorte = $this->profesionalCon('cb.norte', 'consulta_basica', $this->cssNorte, 'Auxiliar administrativo/a');
        $this->tramNorte = $this->profesionalCon('tram.norte', 'tramitacion', $this->cssNorte, 'Auxiliar administrativo/a');

        // La ficha inicial que siembra la migración, fuera: las tarjetas solo muestran el tipo de prueba
        TipoFicha::where('codigo', 'sociodemografica')->update(['activo' => false]);

        $this->tipo = TipoFicha::create([
            'nombre' => 'Datos sociodemográficos (prueba)',
            'codigo' => 'sociodemografica_prueba',
            'uso' => 'persona',
            'activo' => true,
            'schema' => ['campos' => [
                ['id' => 'nacionalidad', 'tipo' => 'catalogo', 'catalogo' => 'comun.pais', 'etiqueta' => 'Nacionalidad', 'obligatorio' => false, 'orden' => 1],
                ['id' => 'ingresos', 'tipo' => 'numero', 'etiqueta' => 'Ingresos netos mensuales', 'unidad' => '€', 'obligatorio' => false, 'orden' => 2],
                ['id' => 'fecha_ingresos', 'tipo' => 'fecha', 'etiqueta' => 'Fecha de referencia', 'obligatorio' => false, 'orden' => 3],
            ]],
        ]);
    }

    #[Test]
    public function tf_sde_26_intervencion_y_consulta_basica_registran_desde_la_interfaz_y_supervision_solo_lee(): void
    {
        // El TSR registra la primera; la consulta básica, en la acogida, la actualiza
        foreach ([[$this->tsNorte, 'Registrar'], [$this->cbNorte, 'Actualizar']] as [$usuario, $boton]) {
            $this->actingAs($usuario);
            Livewire::test(FichasPersona::class, ['ciudadanoId' => $this->sinHistoria->id])
                ->assertSee('Datos sociodemográficos (prueba)')
                ->assertSee($boton);

            Livewire::test(RegistrarFichaPersonaPage::class, ['ciudadano' => $this->sinHistoria->id, 'tipoFicha' => $this->tipo])
                ->set('datos.ingresos', 700)
                ->call('completar')
                ->assertHasNoErrors()
                ->assertRedirect(route('ciudadania.ciudadano.ficha', $this->sinHistoria->id));
        }
        $this->assertSame(
            [$this->cbNorte->id, $this->tsNorte->id],
            Ficha::dePersona($this->sinHistoria->id, $this->tipo->id)->pluck('profesional_id')->all(),
        );

        // Supervisión: ve la tarjeta, sin botón, y la ruta de registro le responde 403
        $this->actingAs($this->supNorte);
        Livewire::test(FichasPersona::class, ['ciudadanoId' => $this->sinHistoria->id])
            ->assertSee('Datos sociodemográficos (prueba)')
            ->assertDontSee('Actualizar');
        $this->get($this->urlRegistrar($this->sinHistoria))->assertForbidden();
    }

    #[Test]
    public function tf_sde_27_sin_permiso_de_lectura_no_hay_tarjeta_y_la_ruta_responde_403(): void
    {
        $this->assertFalse($this->tramNorte->can('ciudadano.leer_fichas_persona'));
        $this->actingAs($this->tramNorte);

        Livewire::test(FichaCiudadanoPage::class, ['ciudadano' => $this->sinHistoria->id])
            ->assertSet('ciudadanoId', $this->sinHistoria->id)
            ->assertDontSee('Datos sociodemográficos (prueba)');
        $this->get($this->urlRegistrar($this->sinHistoria))->assertForbidden();

        $ficha = $this->registrar($this->sinHistoria, ['ingresos' => 500]);
        $this->actingAs($this->tramNorte);
        $this->get(route('intervencion.fichas-persona.ver', ['ciudadano' => $this->sinHistoria->id, 'ficha' => $ficha->id]))->assertForbidden();

        // Con el permiso, la misma ficha del ciudadano sí muestra la tarjeta (el negativo depende del permiso)
        $this->actingAs($this->tsNorte);
        Livewire::test(FichaCiudadanoPage::class, ['ciudadano' => $this->sinHistoria->id])
            ->assertSee('Datos sociodemográficos (prueba)');
    }

    #[Test]
    public function tf_sde_28_consulta_basica_no_ve_la_tarjeta_ni_registra_a_una_protegida(): void
    {
        $this->actingAs($this->cbNorte);
        // Abre la ficha (datos objetivos), pero sin la tarjeta
        Livewire::test(FichaCiudadanoPage::class, ['ciudadano' => $this->marta->id])
            ->assertSet('ciudadanoId', $this->marta->id)
            ->assertDontSee('Datos sociodemográficos (prueba)');
        $this->get($this->urlRegistrar($this->marta))->assertForbidden();

        // Petición forjada al componente de la tarjeta: tampoco
        Livewire::test(FichasPersona::class, ['ciudadanoId' => $this->marta->id])->assertForbidden();

        // Su TSR de referencia sí la ve y registra
        $this->actingAs($this->tsNorte);
        Livewire::test(FichaCiudadanoPage::class, ['ciudadano' => $this->marta->id])
            ->assertSee('Datos sociodemográficos (prueba)');
        $this->get($this->urlRegistrar($this->marta))->assertOk();
    }

    #[Test]
    public function tf_sde_29_la_tarjeta_muestra_la_situacion_vigente_con_etiquetas_fecha_y_autor(): void
    {
        $this->actingAs($this->tsNorte);
        Livewire::test(FichasPersona::class, ['ciudadanoId' => $this->sinHistoria->id])
            ->assertSee('Sin datos registrados.');

        $this->travelTo(now()->setDate(2026, 3, 14));
        $ficha = $this->registrar($this->sinHistoria, ['nacionalidad' => 'MAR', 'ingresos' => 640], noContesta: ['fecha_ingresos']);
        $this->travelBack();

        $this->actingAs($this->supNorte);
        Livewire::test(FichasPersona::class, ['ciudadanoId' => $this->sinHistoria->id])
            ->assertDontSee('Sin datos registrados.')
            ->assertSee('Marruecos')
            ->assertDontSee('MAR</dd>', false)
            ->assertSee('640')
            ->assertSee('No desea contestar')
            ->assertSee(now()->setDate(2026, 3, 14)->translatedFormat('j M Y'))
            ->assertSee($this->tsNorte->nombreVisible());

        // Mostrarla es leerla: queda en la auditoría
        $this->assertTrue(Audit::where('auditable_type', Ficha::class)->where('auditable_id', $ficha->id)
            ->where('accion', AccionAuditEnum::Ver)->where('user_id', $this->supNorte->id)->exists());

        // Un borrador posterior no cambia la situación vigente: se avisa y el botón lo continúa
        $this->registrar($this->sinHistoria, ['ingresos' => 900], completar: false);
        $this->actingAs($this->tsNorte);
        Livewire::test(FichasPersona::class, ['ciudadanoId' => $this->sinHistoria->id])
            ->assertSee('640')
            ->assertSee('Borrador')
            ->assertSee('Continuar borrador');
    }

    #[Test]
    public function tf_sde_29_bis_registrar_actualizar_y_continuar_borrador_desde_la_pagina(): void
    {
        $this->actingAs($this->tsNorte);

        // Registrar: «No desea contestar» vacía el campo y queda marcado
        Livewire::test(RegistrarFichaPersonaPage::class, ['ciudadano' => $this->sinHistoria->id, 'tipoFicha' => $this->tipo])
            ->assertSee('Todos los datos son voluntarios')
            ->set('datos.ingresos', 1200)
            ->set('noContesta.ingresos', true)
            ->assertSet('datos.ingresos', null)
            ->set('datos.nacionalidad', 'MAR')
            ->call('completar')
            ->assertHasNoErrors();
        $primera = Ficha::vigentePara($this->sinHistoria->id, $this->tipo->id);
        $this->assertSame(['ingresos'], $primera->no_contesta);

        // Actualizar: pre-rellenado; al guardar, ficha nueva y la anterior intacta
        Livewire::test(RegistrarFichaPersonaPage::class, ['ciudadano' => $this->sinHistoria->id, 'tipoFicha' => $this->tipo])
            ->assertSet('esActualizacion', true)
            ->assertSet('datos.nacionalidad', 'MAR')
            ->assertSet('noContesta.ingresos', true)
            ->set('noContesta.ingresos', false)
            ->set('datos.ingresos', 800)
            ->call('guardarBorrador')
            ->assertHasNoErrors();
        $borrador = Ficha::dePersona($this->sinHistoria->id, $this->tipo->id)->first();
        $this->assertFalse($borrador->completada);
        $this->assertSame($primera->id, Ficha::vigentePara($this->sinHistoria->id, $this->tipo->id)->id);

        // Continuar el borrador: se retoma el mismo, no se abre otro
        Livewire::test(RegistrarFichaPersonaPage::class, ['ciudadano' => $this->sinHistoria->id, 'tipoFicha' => $this->tipo])
            ->assertSet('borradorId', $borrador->id)
            ->assertSet('datos.ingresos', 800)
            ->call('completar')
            ->assertHasNoErrors();
        $this->assertCount(2, Ficha::dePersona($this->sinHistoria->id, $this->tipo->id)->get());
        $this->assertSame($borrador->id, Ficha::vigentePara($this->sinHistoria->id, $this->tipo->id)->id);
        $this->assertNull($primera->fresh()->datos['ingresos']);

        // Un valor que no es del catálogo se marca en su campo y no se guarda nada
        Livewire::test(RegistrarFichaPersonaPage::class, ['ciudadano' => $this->sinHistoria->id, 'tipoFicha' => $this->tipo])
            ->set('datos.nacionalidad', 'XYZ')
            ->call('completar')
            ->assertHasErrors(['datos.nacionalidad']);
        $this->assertCount(2, Ficha::dePersona($this->sinHistoria->id, $this->tipo->id)->get());
    }

    #[Test]
    public function tf_sde_29_ter_el_pre_relleno_avisa_de_los_valores_que_ya_no_estan_en_la_lista(): void
    {
        $this->registrar($this->sinHistoria, ['nacionalidad' => 'MAR', 'ingresos' => 640]);
        CatalogoSistema::where('grupo', 'comun.pais')->where('clave', 'MAR')->update(['activo' => false]);

        $this->actingAs($this->tsNorte);
        Livewire::test(RegistrarFichaPersonaPage::class, ['ciudadano' => $this->sinHistoria->id, 'tipoFicha' => $this->tipo])
            ->assertSet('datos.nacionalidad', null)
            ->assertSet('datos.ingresos', 640)
            ->assertSee('el valor anterior ya no está en la lista')
            ->assertSee('Nacionalidad (antes: Marruecos)');
    }

    #[Test]
    public function tf_sde_30_el_historial_lista_las_fichas_anteriores_y_cada_una_se_abre_en_lectura(): void
    {
        $this->travelTo(now()->setDate(2026, 1, 10));
        $antigua = $this->registrar($this->sinHistoria, ['ingresos' => 500]);
        $this->travelTo(now()->setDate(2026, 6, 20));
        $vigente = $this->registrar($this->sinHistoria, ['ingresos' => 700]);
        $this->travelBack();

        $this->actingAs($this->supNorte);
        Livewire::test(FichasPersona::class, ['ciudadanoId' => $this->sinHistoria->id])
            ->assertSee('Historial (1)')
            ->assertDontSee(now()->setDate(2026, 1, 10)->translatedFormat('j M Y'))
            ->call('alternarHistorial', $this->tipo->id)
            ->assertSee(now()->setDate(2026, 1, 10)->translatedFormat('j M Y'))
            ->assertSee(route('intervencion.fichas-persona.ver', ['ciudadano' => $this->sinHistoria->id, 'ficha' => $antigua->id]), false);

        $this->get(route('intervencion.fichas-persona.ver', ['ciudadano' => $this->sinHistoria->id, 'ficha' => $antigua->id]))
            ->assertOk()
            ->assertSee('Datos de la persona')
            ->assertDontSee('Ficha de valoración')
            ->assertSee('500')
            ->assertSee('Solo lectura');
        $this->assertTrue(Audit::where('auditable_type', Ficha::class)->where('auditable_id', $antigua->id)
            ->where('accion', AccionAuditEnum::Ver)->where('user_id', $this->supNorte->id)->exists());

        // [negativo] Una ficha bajo otra persona de la ruta: 404, sin datos
        $otra = Ciudadano::factory()->create();
        $this->get(route('intervencion.fichas-persona.ver', ['ciudadano' => $otra->id, 'ficha' => $vigente->id]))->assertNotFound();
    }

    /**
     * URL de la página de registro del tipo de prueba para una persona.
     *
     * @param Ciudadano $ciudadano Persona.
     *
     * @return string
     */
    private function urlRegistrar(Ciudadano $ciudadano): string
    {
        return route('intervencion.fichas-persona.registrar', ['ciudadano' => $ciudadano->id, 'tipoFicha' => $this->tipo->id]);
    }

    /**
     * Registra una ficha de la persona con el servicio como el TSR del CSS Norte.
     *
     * @param Ciudadano $ciudadano Persona.
     * @param array<string, mixed> $datos Valores.
     * @param list<string> $noContesta «No desea contestar».
     * @param bool $completar Si queda completada.
     *
     * @return Ficha
     */
    private function registrar(Ciudadano $ciudadano, array $datos, array $noContesta = [], bool $completar = true): Ficha
    {
        $this->actingAs($this->tsNorte);

        return app(FichaPersonaService::class)->registrar($ciudadano, $this->tipo, $this->tsNorte, $datos, $noContesta, null, $completar);
    }
}
