<?php

namespace Modules\Intervencion\Tests\Feature\Encargo\Actuaciones;

use App\Models\User;
use App\Models\UsuarioUo;
use App\Services\AmbitoUsuario;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Documentos\Enums\EstadoInforme;
use Modules\Documentos\Models\Informe;
use Modules\Escalas\Models\PaseEscala;
use Modules\Intervencion\Enums\EstadoDerivacion;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use Modules\Intervencion\Http\Livewire\RegistrarEscalaPage;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Models\Ficha;
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
 * Grupo B — Vínculo y ámbito (TF-EAC-06 a 10, docs/instrucciones-cli/2026-11-encargo-actuaciones.md).
 *
 * Las actuaciones se guardan por servicios compartidos por la Historia y el encargo; las del
 * encargo llevan `derivacion_id` y ningún plan (EA-5). Solo actúa el profesional asignado con
 * el encargo en curso y adscrito a su centro (EA-4), y solo ve lo suyo dentro de sus encargos
 * (EA-7). Los negativos fallan si se quita la restricción que protegen.
 */
class VinculoAmbitoActuacionesTest extends TestCase
{
    use ActuacionesEncargoTestSetup;
    use RefreshDatabase;

    /**
     * Escenario de las actuaciones del encargo.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarActuaciones();
    }

    /** TF-EAC-06 — Desde la Historia, la anotación y la escala se guardan como antes, con su apunte y sin encargo. */
    #[Test]
    public function tf_eac_06_la_historia_guarda_igual_que_antes(): void
    {
        $this->actingAs($this->tsNorte);

        Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna])
            ->set('formAnotacion', ['contenido' => 'Llamada a la persona', 'visibilidad' => 'privada'])
            ->call('guardarAnotacion')
            ->assertHasNoErrors();

        $anotacion = Apunte::where('tipo', TipoApunte::Anotacion)->sole();
        $this->assertSame('Llamada a la persona', $anotacion->contenido);
        $this->assertSame(VisibilidadApunte::Privada, $anotacion->visibilidad);
        $this->assertSame($this->pisoAna->id, $anotacion->plan_id);
        $this->assertNull($anotacion->derivacion_id);

        Livewire::test(RegistrarEscalaPage::class, ['historia' => $this->historiaAna])
            ->set('tipoEscalaId', $this->escalaPsicologica->id)
            ->set('respuestas', ['item_1' => 1])
            ->call('guardar')
            ->assertHasNoErrors();

        $pase = PaseEscala::sole();
        $this->assertSame(1, $pase->score_total);
        $this->assertNull($pase->derivacion_id);
        $apunte = Apunte::where('tipo', TipoApunte::Escala)->sole();
        $this->assertSame($pase->id, $apunte->apuntable_id);
        $this->assertSame($this->pisoAna->id, $apunte->plan_id);
    }

    /** TF-EAC-07 — Solo actúa la profesional asignada, con el encargo en curso y adscrita a su centro. [negativo] */
    #[Test]
    public function tf_eac_07_quien_actua_en_el_encargo(): void
    {
        // Los encargos se montan antes de actuar como nadie (el cierre deja su apunte)
        $actuaciones = app(ActuacionesEncargo::class);
        $encargo = $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas);
        $cerrado = $this->encargoCerrado($this->historiaMarta, $this->abogadaVallecas);
        $pendiente = $this->encargoPendiente($this->historiaMarta, $this->ciamVallecas);
        $rechazado = $this->encargoPendiente($this->historiaMarta, $this->cssNorte);
        $rechazado->update(['estado' => EstadoDerivacion::Rechazada, 'profesional_responsable_id' => $this->abogadaVallecas->id]);
        $this->assertTrue($actuaciones->puedeActuar($this->abogadaVallecas, $encargo));

        // Otro profesional de encargo del mismo centro
        $this->assertNoActua($this->educadorMulti, $encargo, 'Solo actúa en el encargo');

        // Encargo pendiente (aún sin asignar) y rechazado
        $this->assertNoActua($this->abogadaVallecas, $pendiente, 'Solo actúa en el encargo');
        $this->assertNoActua($this->abogadaVallecas, $rechazado->refresh(), 'no está en curso');

        // Encargo cerrado: ya no se registra nada (EA-11)
        $this->assertNoActua($this->abogadaVallecas, $cerrado, 'no está en curso');

        // Sin adscripción al centro del encargo (RN-15)
        UsuarioUo::where('usuario_id', $this->abogadaVallecas->id)->update(['fecha_fin' => today()->subDay()->toDateString()]);
        app(AmbitoUsuario::class)->olvidar();
        $this->assertNoActua($this->abogadaVallecas, $encargo, 'adscrito');

        $this->assertSame(0, Apunte::withoutGlobalScopes()->where('tipo', TipoApunte::Anotacion)->count());
    }

    /** TF-EAC-08 — La abogada no ve las fichas, pases, informes ni apuntes de la Historia hechos por el TSR. [negativo] */
    #[Test]
    public function tf_eac_08_no_ve_lo_de_la_historia(): void
    {
        $encargo = $this->encargoAceptado($this->historiaAna, $this->abogadaVallecas);
        $this->areaJuridica->tiposEscala()->attach($this->escalaPsicologica);

        // Lo del TSR, desde la Historia
        $this->actingAs($this->tsNorte);
        $deHistoria = ContextoActuacion::desdeHistoria($this->historiaAna, $this->tsNorte, $this->pisoAna->id);
        $anotacionTsr = app(AnotacionService::class)->registrar($deHistoria, 'Valoración del TSR', VisibilidadApunte::Profesionales);
        $fichaTsr = app(ValoracionService::class)->registrar($deHistoria, $this->fichaJuridica, ['situacion' => 'Del TSR']);
        $paseTsr = app(PaseEscalaService::class)->registrar($deHistoria, $this->escalaPsicologica, ['item_1' => 1]);
        $informeTsr = $this->informe($this->tsNorte, null);

        // Lo suyo, desde el encargo
        $this->actingAs($this->abogadaVallecas);
        $desdeEncargo = ContextoActuacion::desdeEncargo($encargo, $this->abogadaVallecas);
        $anotacion = app(AnotacionService::class)->registrar($desdeEncargo, 'Primera entrevista jurídica', VisibilidadApunte::Profesionales);
        $ficha = app(ValoracionService::class)->registrar($desdeEncargo, $this->fichaJuridica, ['situacion' => 'De la abogada']);
        $pase = app(PaseEscalaService::class)->registrar($desdeEncargo, $this->escalaPsicologica, ['item_1' => 0]);
        $informe = $this->informe($this->abogadaVallecas, $encargo);

        $abogada = Gate::forUser($this->abogadaVallecas);
        $this->assertFalse($abogada->allows('view', $fichaTsr));
        $this->assertFalse($abogada->allows('view', $paseTsr));
        $this->assertFalse($abogada->allows('view', $informeTsr));
        $this->assertTrue($abogada->allows('view', $ficha));
        $this->assertTrue($abogada->allows('view', $pase));
        $this->assertTrue($abogada->allows('view', $informe));

        // Apuntes: ni por el scope ni en la lista del encargo
        $apuntesVisibles = Apunte::where('historia_id', $this->historiaAna->id)->pluck('id');
        $this->assertNotContains($anotacionTsr->id, $apuntesVisibles);
        $this->assertContains($anotacion->id, $apuntesVisibles);
        $this->assertEqualsCanonicalizing(
            Apunte::withoutGlobalScopes()->where('derivacion_id', $encargo->id)->pluck('id')->all(),
            app(ActuacionesEncargo::class)->visibles($this->abogadaVallecas, $encargo)->pluck('id')->all(),
        );
        $this->assertNotContains($anotacionTsr->id, app(ActuacionesEncargo::class)->visibles($this->abogadaVallecas, $encargo)->pluck('id'));

        // El TSR, con acceso a la Historia, ve lo del encargo (EA-6)
        $this->assertTrue(Gate::forUser($this->tsNorte)->allows('view', $ficha));
        $this->assertTrue(Gate::forUser($this->tsNorte)->allows('view', $pase));
    }

    /** TF-EAC-09 — No ve las actuaciones de otro profesional en otro encargo de la misma persona. [negativo] */
    #[Test]
    public function tf_eac_09_no_ve_otro_encargo_de_la_persona(): void
    {
        // Un solo encargo en curso por persona y centro: el de la abogada ya está cerrado (sigue siendo suyo)
        $encargoAbogada = $this->encargoCerrado($this->historiaAna, $this->abogadaVallecas);
        $encargoEducador = $this->encargoAceptado($this->historiaAna, $this->educadorMulti);

        $this->actingAs($this->educadorMulti);
        $deEducador = ContextoActuacion::desdeEncargo($encargoEducador, $this->educadorMulti);
        $anotacionEducador = app(AnotacionService::class)->registrar($deEducador, 'Sesión socioeducativa', VisibilidadApunte::Profesionales);
        $fichaEducador = app(ValoracionService::class)->registrar($deEducador, $this->fichaJuridica, ['situacion' => 'Del educador']);

        $this->actingAs($this->abogadaVallecas);
        $this->assertFalse(Gate::forUser($this->abogadaVallecas)->allows('view', $fichaEducador));
        $this->assertTrue(app(ActuacionesEncargo::class)->visibles($this->abogadaVallecas, $encargoEducador)->isEmpty());
        $this->assertTrue(app(ActuacionesEncargo::class)->visibles($this->abogadaVallecas, $encargoAbogada)->isEmpty());
        $this->assertNotContains($anotacionEducador->id, Apunte::where('historia_id', $this->historiaAna->id)->pluck('id'));

        // El educador sí lo ve
        $this->assertTrue(Gate::forUser($this->educadorMulti)->allows('view', $fichaEducador));
        // Su anotación y el apunte de su ficha
        $this->assertEqualsCanonicalizing(
            Apunte::withoutGlobalScopes()->where('derivacion_id', $encargoEducador->id)->pluck('id')->all(),
            app(ActuacionesEncargo::class)->visibles($this->educadorMulti, $encargoEducador)->pluck('id')->all(),
        );
        $this->assertCount(2, app(ActuacionesEncargo::class)->visibles($this->educadorMulti, $encargoEducador));
    }

    /** TF-EAC-10 — Las actuaciones del encargo llevan `derivacion_id` y ningún plan, aunque el encargo naciera del plan principal. */
    #[Test]
    public function tf_eac_10_vinculo_al_encargo_sin_plan(): void
    {
        $encargo = app(EncargoService::class)->encargar($this->historiaAna, $this->pisoAna, null, $this->ciamVallecas, $this->areaJuridica, 'Orientación jurídica', [], $this->tsNorte);
        app(EncargoService::class)->aceptar($encargo, $this->abogadaVallecas, $this->directoraVallecas);
        $encargo->refresh();
        $this->assertSame($this->pisoAna->id, $encargo->plan_origen_id);
        $this->areaJuridica->tiposEscala()->attach($this->escalaPsicologica);

        $this->actingAs($this->abogadaVallecas);
        $contexto = ContextoActuacion::desdeEncargo($encargo, $this->abogadaVallecas);
        $anotacion = app(AnotacionService::class)->registrar($contexto, 'Anotación del encargo', VisibilidadApunte::Profesionales);
        $ficha = app(ValoracionService::class)->registrar($contexto, $this->fichaJuridica, ['situacion' => 'Pendiente de juicio']);
        $pase = app(PaseEscalaService::class)->registrar($contexto, $this->escalaPsicologica, ['item_1' => 1]);

        $this->assertSame($encargo->id, $ficha->derivacion_id);
        $this->assertSame($this->fichaJuridica->schema, $ficha->schema_snapshot);
        $this->assertSame($encargo->id, $pase->derivacion_id);
        $this->assertSame($encargo->id, $anotacion->derivacion_id);

        $apuntes = Apunte::withoutGlobalScopes()->where('derivacion_id', $encargo->id)->get();
        $this->assertCount(3, $apuntes);
        $this->assertTrue($apuntes->every(fn (Apunte $a) => $a->plan_id === null && $a->historia_id === $this->historiaAna->id));
        $this->assertEqualsCanonicalizing(
            [TipoApunte::Anotacion, TipoApunte::Valoracion, TipoApunte::Escala],
            $apuntes->pluck('tipo')->all(),
        );
    }

    /**
     * Comprueba que el usuario no puede actuar en el encargo y que el servicio lo rechaza.
     *
     * @param User $usuario
     * @param Derivacion $encargo
     * @param string $motivo Fragmento del mensaje esperado.
     *
     * @return void
     */
    private function assertNoActua(User $usuario, Derivacion $encargo, string $motivo): void
    {
        $this->assertFalse(app(ActuacionesEncargo::class)->puedeActuar($usuario, $encargo));

        $this->actingAs($usuario);
        try {
            app(AnotacionService::class)->registrar(ContextoActuacion::desdeEncargo($encargo, $usuario), 'Forjada', VisibilidadApunte::Profesionales);
            $this->fail('Se ha registrado una actuación que debía rechazarse.');
        } catch (DomainException $e) {
            $this->assertStringContainsString($motivo, $e->getMessage());
        }
    }

    /**
     * Borrador de informe de Ana con la plantilla jurídica.
     *
     * @param User $autor
     * @param Derivacion|null $encargo Encargo desde el que se redacta, si lo hay.
     *
     * @return Informe
     */
    private function informe(User $autor, ?Derivacion $encargo): Informe
    {
        return Informe::create([
            'plantilla_id' => $this->plantillaJuridica->id,
            'historia_social_id' => $this->historiaAna->id,
            'ciudadano_id' => $this->ana->id,
            'autor_id' => $autor->id,
            'estado' => EstadoInforme::Borrador->value,
            'contenido' => ['valoracion' => 'Texto'],
            'derivacion_id' => $encargo?->id,
        ]);
    }
}
