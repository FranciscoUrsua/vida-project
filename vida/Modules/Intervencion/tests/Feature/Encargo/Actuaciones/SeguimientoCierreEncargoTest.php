<?php

namespace Modules\Intervencion\Tests\Feature\Encargo\Actuaciones;

use App\Models\User;
use App\Models\UsuarioUo;
use App\Services\AmbitoUsuario;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Documentos\Models\Informe;
use Modules\Documentos\Services\InformeService;
use Modules\Escalas\Enums\EstadoPase;
use Modules\Escalas\Models\PaseEscala;
use Modules\Intervencion\Enums\EstadoDerivacion;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use Modules\Intervencion\Http\Livewire\EncargoPage;
use Modules\Intervencion\Http\Livewire\EncargosProfesionales;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Services\Actuaciones\AnotacionService;
use Modules\Intervencion\Services\Actuaciones\ContextoActuacion;
use Modules\Intervencion\Services\Actuaciones\PaseEscalaService;
use Modules\Intervencion\Services\Actuaciones\ValoracionService;
use Modules\Intervencion\Services\EncargoService;
use Modules\Intervencion\Tests\Concerns\ActuacionesEncargoTestSetup;
use Modules\Mensajes\Models\Alerta;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo F — Seguimiento y cierre (TF-EAC-31 a 36, docs/instrucciones-cli/2026-11-encargo-actuaciones.md).
 *
 * El TSR sigue en `EncargosProfesionales` lo que la profesional registra en cada encargo, sin
 * sus anotaciones privadas y sin un aviso por actuación (EA-12). El encargo no se cierra con
 * borradores abiertos (EA-11) y, cerrado, no admite actuaciones. Las actuaciones cuentan en el
 * centro del encargo aunque el centro activo de la profesional sea otro (D1.6). Los negativos
 * fallan si se quita la restricción que protegen.
 */
class SeguimientoCierreEncargoTest extends TestCase
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

        $this->areaJuridica->tiposEscala()->attach($this->escalaPsicologica);
        $this->encargo = $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas);
    }

    /**
     * Restaura el reloj (TF-EAC-36 lo fija).
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** TF-EAC-31 — El TSR ve en `EncargosProfesionales` las actuaciones del encargo con enlace al timeline. */
    #[Test]
    public function tf_eac_31_el_tsr_sigue_las_actuaciones(): void
    {
        $this->actingAs($this->abogadaVallecas);
        $contexto = ContextoActuacion::desdeEncargo($this->encargo, $this->abogadaVallecas);
        $anotacion = app(AnotacionService::class)->registrar($contexto, 'Primera entrevista jurídica', VisibilidadApunte::Profesionales);
        app(ValoracionService::class)->registrar($contexto, $this->fichaJuridica, ['situacion' => 'Demanda presentada']);

        $this->actingAs($this->tsNorte);
        $urlApunte = route('intervencion.ciudadano.show', ['historia' => $this->historiaAna->id, 'apunte' => $anotacion->id]);
        Livewire::test(EncargosProfesionales::class, ['historiaId' => $this->historiaAna->id])
            ->assertSee('Primera entrevista jurídica')
            ->assertSee('Situación jurídica (test)')
            ->assertSee($this->abogadaVallecas->name)
            ->assertSeeHtml(e($urlApunte));

        // El enlace abre la Historia Social con el detalle del apunte
        Livewire::withQueryParams(['apunte' => $anotacion->id])
            ->test(CiudadanoPage::class, ['historia' => $this->historiaAna])
            ->assertSet('modalApunteId', $anotacion->id);
    }

    /** TF-EAC-32 — La lista del TSR no incluye las anotaciones privadas de la abogada. [negativo] */
    #[Test]
    public function tf_eac_32_sin_anotaciones_privadas(): void
    {
        $this->actingAs($this->abogadaVallecas);
        $contexto = ContextoActuacion::desdeEncargo($this->encargo, $this->abogadaVallecas);
        app(AnotacionService::class)->registrar($contexto, 'Nota para profesionales', VisibilidadApunte::Profesionales);
        $privada = app(AnotacionService::class)->registrar($contexto, 'Impresión personal de la abogada', VisibilidadApunte::Privada);

        $this->actingAs($this->tsNorte);
        Livewire::test(EncargosProfesionales::class, ['historiaId' => $this->historiaAna->id])
            ->assertSee('Nota para profesionales')
            ->assertDontSee('Impresión personal de la abogada');

        // Tampoco abriendo su id en la Historia
        Livewire::withQueryParams(['apunte' => $privada->id])
            ->test(CiudadanoPage::class, ['historia' => $this->historiaAna])
            ->assertSet('modalApunteId', null);
    }

    /** TF-EAC-33 — No se cierra con borradores de informe o ficha abiertos; completados o descartados, sí. [negativo] */
    #[Test]
    public function tf_eac_33_no_se_cierra_con_borradores(): void
    {
        $this->actingAs($this->abogadaVallecas);
        $borrador = app(InformeService::class)->crearDesdeEncargo($this->plantillaJuridica, $this->encargo, $this->abogadaVallecas);
        $fichaBorrador = Ficha::create([
            'historia_id' => $this->historiaAna->id,
            'tipo_ficha_id' => $this->fichaJuridica->id,
            'schema_snapshot' => $this->fichaJuridica->schema,
            'completada' => false,
            'profesional_id' => $this->abogadaVallecas->id,
            'derivacion_id' => $this->encargo->id,
        ]);

        $pagina = Livewire::test(EncargoPage::class, ['encargo' => $this->encargo->id])
            ->assertSee('Antes de cerrar')
            ->assertSee('Informe jurídico (test)')
            ->assertSee('Situación jurídica (test)')
            ->set('resultado', 'Recurso presentado.')
            ->call('cerrar')
            ->assertHasErrors('resultado');
        $this->assertSame(EstadoDerivacion::Aceptada, $this->encargo->refresh()->estado);

        try {
            app(EncargoService::class)->cerrar($this->encargo, 'Recurso presentado.', $this->abogadaVallecas);
            $this->fail('No debía cerrarse con borradores abiertos');
        } catch (DomainException $e) {
            $this->assertStringContainsString('borradores', $e->getMessage());
        }

        // Descarta el borrador del informe desde el encargo y completa la ficha (las fichas no se
        // guardan en borrador desde la interfaz: el borrador es de datos)
        $pagina->call('eliminarBorrador', $borrador->id)->assertHasNoErrors();
        $this->assertNull(Informe::find($borrador->id));
        $fichaBorrador->update(['completada' => true]);

        // Un pase de escala en borrador también lo impide
        $pase = PaseEscala::create([
            'tipo_escala_id' => $this->escalaPsicologica->id,
            'historia_id' => $this->historiaAna->id,
            'profesional_id' => $this->abogadaVallecas->id,
            'fecha' => today()->toDateString(),
            'respuestas' => [],
            'estado' => EstadoPase::Borrador,
            'derivacion_id' => $this->encargo->id,
        ]);
        $pagina->set('resultado', 'Recurso presentado.')->call('cerrar')->assertHasErrors('resultado');
        $this->assertSame(EstadoDerivacion::Aceptada, $this->encargo->refresh()->estado);

        $pase->update(['estado' => EstadoPase::Completado]);
        $pagina->set('resultado', 'Recurso presentado.')
            ->call('cerrar')
            ->assertHasNoErrors();
        $this->assertSame(EstadoDerivacion::Cerrada, $this->encargo->refresh()->estado);
    }

    /** TF-EAC-34 — Encargo cerrado: sin herramientas y las acciones forjadas, rechazadas. [negativo] */
    #[Test]
    public function tf_eac_34_cerrado_sin_herramientas(): void
    {
        app(EncargoService::class)->cerrar($this->encargo, 'Recurso presentado.', $this->abogadaVallecas);
        $this->actingAs($this->abogadaVallecas);

        $pagina = Livewire::test(EncargoPage::class, ['encargo' => $this->encargo->id])
            ->assertOk()
            ->assertDontSee('Guardar anotación')
            ->assertDontSee('Ficha de valoración')
            ->assertDontSeeHtml('id="encargo-herramientas"')
            ->assertSee('Recurso presentado.');

        $pagina->set('formAnotacion.contenido', 'Forjada')->call('guardarAnotacion')->assertHasErrors('formAnotacion.contenido');
        $pagina->call('abrirHerramienta', 'valoracion')->assertForbidden();
        $this->assertSame(0, Apunte::withoutGlobalScopes()->where('tipo', TipoApunte::Anotacion)->count());

        foreach (['intervencion.encargos.valoracion', 'intervencion.encargos.escala', 'intervencion.encargos.informe.nuevo'] as $ruta) {
            $this->get(route($ruta, $this->encargo->id))->assertForbidden();
        }

        foreach ([
            fn () => app(AnotacionService::class)->registrar(ContextoActuacion::desdeEncargo($this->encargo, $this->abogadaVallecas), 'Forjada', VisibilidadApunte::Profesionales),
            fn () => app(InformeService::class)->crearDesdeEncargo($this->plantillaJuridica, $this->encargo, $this->abogadaVallecas),
        ] as $forjada) {
            try {
                $forjada();
                $this->fail('Con el encargo cerrado no debía registrarse nada');
            } catch (DomainException $e) {
                $this->assertStringContainsString('no está en curso', $e->getMessage());
            }
        }
    }

    /** TF-EAC-35 — Ningún aviso al TSR por actuación; sí al cerrar. */
    #[Test]
    public function tf_eac_35_aviso_solo_al_cerrar(): void
    {
        $antes = Alerta::pendientesPara($this->tsNorte)->count();

        $this->actingAs($this->abogadaVallecas);
        $contexto = ContextoActuacion::desdeEncargo($this->encargo, $this->abogadaVallecas);
        app(AnotacionService::class)->registrar($contexto, 'Entrevista', VisibilidadApunte::Profesionales);
        app(ValoracionService::class)->registrar($contexto, $this->fichaJuridica, ['situacion' => 'Demanda']);
        app(PaseEscalaService::class)->registrar($contexto, $this->escalaPsicologica, ['item_1' => 1]);
        $this->assertSame($antes, Alerta::pendientesPara($this->tsNorte)->count());

        app(EncargoService::class)->cerrar($this->encargo, 'Recurso presentado.', $this->abogadaVallecas);
        $this->assertSame($antes + 1, Alerta::pendientesPara($this->tsNorte)->count());
        $this->assertTrue(Alerta::pendientesPara($this->tsNorte)->where('titulo', 'Encargo profesional cerrado')->exists());
    }

    /** TF-EAC-36 — La actuación cuenta en el centro del encargo aunque el centro activo de la abogada sea otro. [negativo] */
    #[Test]
    public function tf_eac_36_centro_del_encargo(): void
    {
        // Mañana en el CSS Norte, tarde en el CIAM: a las 10:00 su centro activo es el CSS
        $this->adscribir($this->abogadaVallecas, $this->cssNorte->unidadOrganizativa);
        $this->perfil($this->abogadaVallecas, $this->cssNorte, '09:00', '14:00');
        $this->perfil($this->abogadaVallecas, $this->ciamVallecas, '15:00', '20:00');
        Carbon::setTestNow(today()->setTime(10, 0));
        app(AmbitoUsuario::class)->olvidar();
        $this->assertSame($this->cssNorte->id, app(CentroDeUsuario::class)->centroActivo($this->abogadaVallecas)?->id);

        $this->actingAs($this->abogadaVallecas);
        $contexto = ContextoActuacion::desdeEncargo($this->encargo, $this->abogadaVallecas);
        $this->assertSame($this->ciamVallecas->id, $contexto->centro()?->id);
        app(AnotacionService::class)->registrar($contexto, 'Desde el CSS, para el CIAM', VisibilidadApunte::Profesionales);
        $this->assertSame(1, Apunte::withoutGlobalScopes()->where('tipo', TipoApunte::Anotacion)->count());

        // Sin adscripción al CIAM no actúa, aunque su centro activo (el CSS) sí sea suyo
        $uoCiam = $this->ciamVallecas->unidad_organizativa_id;
        UsuarioUo::where('usuario_id', $this->abogadaVallecas->id)->where('unidad_organizativa_id', $uoCiam)
            ->update(['fecha_fin' => today()->subDay()->toDateString()]);
        app(AmbitoUsuario::class)->olvidar();
        $this->assertSame($this->cssNorte->id, app(CentroDeUsuario::class)->centroActivo($this->abogadaVallecas)?->id);

        $this->expectException(DomainException::class);
        app(AnotacionService::class)->registrar(ContextoActuacion::desdeEncargo($this->encargo, $this->abogadaVallecas), 'Rechazada', VisibilidadApunte::Profesionales);
    }

    /**
     * Perfil horario vigente con una única franja hoy en un centro.
     *
     * @param User $usuario
     * @param Centro $centro
     * @param string $inicio
     * @param string $fin
     *
     * @return PerfilHorarioProfesional
     */
    private function perfil(User $usuario, Centro $centro, string $inicio, string $fin): PerfilHorarioProfesional
    {
        return PerfilHorarioProfesional::factory()->create([
            'usuario_id' => $usuario->id,
            'centro_id' => $centro->id,
            'horario_habitual' => [(string) today()->isoWeekday() => [['inicio' => $inicio, 'fin' => $fin]]],
            'vigente_desde' => today()->subMonth()->toDateString(),
            'vigente_hasta' => null,
            'activo' => true,
        ]);
    }
}
