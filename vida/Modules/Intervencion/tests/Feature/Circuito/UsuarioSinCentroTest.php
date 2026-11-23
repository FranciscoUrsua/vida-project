<?php

namespace Modules\Intervencion\Tests\Feature\Circuito;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UsuarioUo;
use App\Services\AmbitoUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Ciudadania\Http\Livewire\FichaCiudadanoPage;
use Modules\Documentos\Http\Livewire\DocumentosCiudadano;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use Modules\Intervencion\Http\Livewire\DerivacionesPlan;
use Modules\Intervencion\Http\Livewire\PlanPage;
use Modules\Intervencion\Http\Livewire\PrescribirRecursoModal;
use Modules\Intervencion\Http\Livewire\RegistrarValoracionPage;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Services\AperturaHistoriaService;
use Modules\Intervencion\Tests\Concerns\CircuitoTestSetup;
use Modules\Prestaciones\Http\Livewire\SolicitarPrestacion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo C — Usuario sin centro: Historia Social en lectura (R1 de la ronda 1 de staging,
 * TF-STG-12 a 16). RN-15 se aplica a toda actuación sobre una persona; un usuario sin ningún
 * centro (aquí `$tecnicaIgualdad`, intervención en la UO funcional de los CIAM) consulta,
 * pero no actúa.
 */
class UsuarioSinCentroTest extends TestCase
{
    use CircuitoTestSetup;
    use RefreshDatabase;

    /** Mensaje de RN-15 para quien no tiene ningún centro. */
    private const RN15 = 'Para atender a ciudadanos tienes que estar adscrito a un centro.';

    /** Persona con historia en el CIAM de Vallecas, dentro del ámbito de la técnica. */
    private Ciudadano $vecina;

    /** Su Historia Social. */
    private HistoriaSocial $historiaVecina;

    /** PIA de la psicóloga de Vallecas: un plan de otra profesional. */
    private PlanDeIntervencion $piaVecina;

    /**
     * Escenario del circuito más una persona del CIAM de Vallecas con un plan de la psicóloga.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCircuito();

        [$this->vecina, $this->historiaVecina] = $this->personaEnCentro($this->ciamVallecas, referencia: $this->psicologaVallecas);
        $this->planPia->update(['admite_entrada_directa' => true]);
        $this->piaVecina = $this->planActivo($this->historiaVecina, $this->planPia, $this->ciamVallecas, $this->psicologaVallecas);
    }

    /** TF-STG-12 — Sin centro, la Historia Social no ofrece ninguna actuación. [negativo] */
    #[Test]
    public function tf_stg_12_historia_en_lectura_sin_acciones_de_actuacion(): void
    {
        $respuesta = $this->actingAs($this->tecnicaIgualdad)
            ->get(route('intervencion.ciudadano.show', $this->historiaVecina->id));

        $respuesta->assertOk()
            ->assertSee('Consulta en modo lectura')
            ->assertDontSee("seleccionarHerramienta('entrevista')", false)
            ->assertDontSee("seleccionarHerramienta('anotacion')", false)
            ->assertDontSee("seleccionarHerramienta('gestion')", false)
            ->assertDontSee("seleccionarHerramienta('valoracion')", false)
            ->assertDontSee("seleccionarHerramienta('escala')", false)
            ->assertDontSee("seleccionarHerramienta('cita')", false)
            ->assertDontSee('abrirPrescribirRecurso', false)
            ->assertDontSee('+ Abrir plan')
            // Los informes se consultan
            ->assertSee("seleccionarHerramienta('informes')", false);

        // Quien tiene centro sí ve la caja de herramientas completa
        $this->actingAs($this->psicologaVallecas)
            ->get(route('intervencion.ciudadano.show', $this->historiaVecina->id))
            ->assertOk()
            ->assertDontSee('Consulta en modo lectura')
            ->assertSee("seleccionarHerramienta('entrevista')", false)
            ->assertSee('+ Abrir plan');
    }

    /**
     * Puntos de entrada de las actuaciones de R1 que se abren dentro de una pantalla (acciones
     * Livewire): cada uno se rechaza al abrir con un aviso con el mensaje de RN-15.
     *
     * @return array<string, array{0: string}>
     */
    public static function actuacionesLivewire(): array
    {
        return [
            'entrevista' => ['entrevista'],
            'anotación' => ['anotacion'],
            'gestión' => ['gestion'],
            'valoración (herramienta)' => ['valoracion'],
            'escala (herramienta)' => ['escala'],
            'solicitud de cita' => ['cita'],
            'prescripción (desde la historia)' => ['prescripcion_historia'],
            'prescripción (modal)' => ['prescripcion_modal'],
            'solicitud de prestación' => ['solicitud_prestacion'],
            'derivación' => ['derivacion'],
            'subida de documentos' => ['documentos'],
            'atención (ficha)' => ['atencion'],
        ];
    }

    /**
     * TF-STG-13 — Cada actuación se rechaza al abrir el formulario, con el mensaje de RN-15. [negativo]
     *
     * @param string $actuacion Punto de entrada.
     *
     * @return void
     */
    #[Test]
    #[DataProvider('actuacionesLivewire')]
    public function tf_stg_13_rechazo_al_abrir_en_pantalla(string $actuacion): void
    {
        $this->actingAs($this->tecnicaIgualdad);

        $prueba = match ($actuacion) {
            'entrevista', 'anotacion', 'gestion', 'valoracion', 'escala', 'cita' => Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaVecina])
                ->call('seleccionarHerramienta', $actuacion)
                ->assertSet('herramientaActiva', null),
            'prescripcion_historia' => Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaVecina])
                ->call('abrirPrescribirRecurso')
                ->assertNotDispatched('abrir-prescribir-recurso'),
            'prescripcion_modal' => Livewire::test(PrescribirRecursoModal::class, ['historiaId' => $this->historiaVecina->id])
                ->call('abrir')
                ->assertSet('abierto', false),
            'solicitud_prestacion' => Livewire::test(SolicitarPrestacion::class, ['historiaId' => $this->historiaVecina->id])
                ->call('abrir')
                ->assertSet('abierto', false),
            'derivacion' => Livewire::test(DerivacionesPlan::class, ['planId' => $this->piaVecina->id])
                ->call('abrir')
                ->assertSet('modalAbierto', false),
            'documentos' => Livewire::test(DocumentosCiudadano::class, ['ciudadanoId' => $this->vecina->id])
                ->call('abrirAlta')
                ->assertSet('modalAbierto', false),
            'atencion' => Livewire::test(FichaCiudadanoPage::class, ['ciudadano' => $this->vecina->id])
                ->call('abrirModalAtencion')
                ->assertSet('modalAtencionAbierto', false),
        };

        $prueba->assertDispatched('toast', message: self::RN15, type: 'danger');
    }

    /**
     * Pantallas completas de actuación: se rechaza la apertura con un 403 que da el motivo.
     *
     * @return array<string, array{0: string}>
     */
    public static function pantallasDeActuacion(): array
    {
        return [
            'valoración' => ['valoracion'],
            'escala' => ['escala'],
            'nuevo plan' => ['plan'],
            'nuevo informe' => ['informe'],
        ];
    }

    /**
     * TF-STG-13 bis — Las pantallas completas de actuación no se abren, con el mensaje de RN-15. [negativo]
     *
     * @param string $pantalla Pantalla.
     *
     * @return void
     */
    #[Test]
    #[DataProvider('pantallasDeActuacion')]
    public function tf_stg_13_bis_rechazo_al_abrir_pantalla_completa(string $pantalla): void
    {
        $url = match ($pantalla) {
            'valoracion' => route('intervencion.valoracion.nueva', $this->historiaVecina->id),
            'escala' => route('intervencion.escala.nueva', $this->historiaVecina->id),
            'plan' => route('intervencion.plan.crear', ['historia' => $this->historiaVecina->id]),
            'informe' => route('intervencion.informes.nuevo', $this->historiaVecina->id),
        };

        $this->actingAs($this->tecnicaIgualdad)->get($url)
            ->assertForbidden()
            ->assertSee(self::RN15)
            ->assertSee('Volver al inicio');
    }

    /**
     * TF-STG-13 ter — También al guardar: quien tenía el formulario abierto y ya no tiene centro no
     * guarda, y la valoración no deja escrituras parciales (EST-04, ficha 14 de staging). [negativo]
     */
    #[Test]
    public function tf_stg_13_ter_al_guardar_se_rechaza_sin_escrituras_parciales(): void
    {
        $this->actingAs($this->psicologaVallecas);
        $valoracion = Livewire::test(RegistrarValoracionPage::class, ['historia' => $this->historiaVecina])
            ->set('tipoFichaId', $this->fichaVG->id);
        $plan = Livewire::test(PlanPage::class, ['plan' => $this->piaVecina])
            ->set('periodicidadSeguimiento', 'mensual');

        // Pierde la adscripción con los formularios abiertos (sin eventos de modelo: no es una salida)
        UsuarioUo::where('usuario_id', $this->psicologaVallecas->id)->update(['fecha_fin' => now()->subDay()]);
        app(AmbitoUsuario::class)->olvidar();

        $valoracion->call('guardarDefinitivo')
            ->assertDispatched('toast', message: self::RN15, type: 'danger');
        $this->assertSame(0, Ficha::where('historia_id', $this->historiaVecina->id)->count());
        $this->assertSame(0, Apunte::withoutGlobalScopes()->where('historia_id', $this->historiaVecina->id)->count());

        // Seguimiento y edición del plan
        $plan->call('guardarSeguimiento')
            ->assertDispatched('toast', message: self::RN15, type: 'danger');
        $this->assertNotSame('mensual', $this->piaVecina->fresh()->periodicidad_seguimiento);
    }

    /** TF-STG-14 — Las actuaciones de otras profesionales se abren en lectura. */
    #[Test]
    public function tf_stg_14_actuaciones_de_otros_en_lectura(): void
    {
        // Plan de la psicóloga: sin acciones de edición
        $this->actingAs($this->tecnicaIgualdad)
            ->get(route('intervencion.plan.show', $this->piaVecina))
            ->assertOk()
            ->assertDontSee('wire:click="guardarPlan"', false)
            ->assertDontSee('wire:click="abrirModalObjetivo"', false)
            ->assertDontSee('wire:click="abrirModalCierre"', false)
            ->assertDontSee('wire:click="abrirDrawer"', false);

        // Valoración de la psicóloga: se ve sin formulario de edición
        $ficha = Ficha::create([
            'historia_id' => $this->historiaVecina->id,
            'tipo_ficha_id' => $this->fichaVG->id,
            'schema_snapshot' => ['campos' => []],
            'completada' => true,
            'profesional_id' => $this->psicologaVallecas->id,
        ]);

        $this->actingAs($this->tecnicaIgualdad)
            ->get(route('intervencion.ficha.show', ['historia' => $this->historiaVecina->id, 'ficha' => $ficha->id]))
            ->assertOk()
            ->assertDontSee('wire:click="guardar', false);

        // [negativo] La responsable del plan sí lo edita
        $this->actingAs($this->psicologaVallecas)
            ->get(route('intervencion.plan.show', $this->piaVecina))
            ->assertOk()
            ->assertSee('wire:click="guardarPlan"', false);
    }

    /** TF-STG-15 — Sin centro no se abre una Historia Social. [negativo] */
    #[Test]
    public function tf_stg_15_no_abre_historias(): void
    {
        $persona = Ciudadano::factory()->create();

        try {
            app(AperturaHistoriaService::class)->abrir($persona->id, $this->tecnicaIgualdad);
            $this->fail('Sin centro no se abre una Historia Social');
        } catch (\DomainException $e) {
            $this->assertSame(self::RN15, $e->getMessage());
        }

        $this->assertSame(0, HistoriaSocial::withoutGlobalScopes()->where('ciudadano_id', $persona->id)->count());

        // Y la ficha no ofrece el botón
        $this->actingAs($this->tecnicaIgualdad);
        Livewire::test(FichaCiudadanoPage::class, ['ciudadano' => $persona->id])
            ->assertSet('puedeAbrirHistoria', false);
    }

    /** TF-STG-16 — Sin regresión: la TS adscrita sigue actuando. */
    #[Test]
    public function tf_stg_16_sin_regresion_para_quien_tiene_centro(): void
    {
        $this->actingAs($this->tsNorte);

        Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna])
            ->call('seleccionarHerramienta', 'anotacion')
            ->assertSet('herramientaActiva', 'anotacion')
            ->set('formAnotacion.contenido', 'Llamada de seguimiento')
            ->call('guardarAnotacion')
            ->assertNotDispatched('toast');

        $this->assertTrue(Apunte::withoutGlobalScopes()->where('historia_id', $this->historiaAna->id)->where('contenido', 'Llamada de seguimiento')->exists());

        Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna])
            ->call('seleccionarHerramienta', 'entrevista')
            ->assertSet('herramientaActiva', 'entrevista');

        $this->get(route('intervencion.valoracion.nueva', $this->historiaAna->id))->assertOk();
        $this->get(route('intervencion.plan.crear', ['historia' => $this->historiaAna->id]))->assertOk();

        Livewire::test(PlanPage::class, ['plan' => $this->pisoAna])
            ->set('periodicidadSeguimiento', 'mensual')
            ->call('guardarSeguimiento')
            ->assertNotDispatched('toast');
        $this->assertSame('mensual', $this->pisoAna->fresh()->periodicidad_seguimiento);
        $this->assertSame(EstadoPlan::Activo, $this->pisoAna->fresh()->estado);
    }
}
