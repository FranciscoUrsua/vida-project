<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\AvisoContrasenaOlvidada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Centro\Tests\Concerns\TipoCentroTestSetup;
use Modules\Mensajes\Models\Alerta;
use Modules\Mensajes\Models\AlertaDestinatario;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * «¿Olvidaste tu contraseña?» sin correo (27 nov 2026): avisa a la supervisión del centro y a
 * la administración de usuarios de su ámbito, sin revelar si la cuenta existe.
 *
 * Datos: `$tsNorte` (intervención, CSS Norte); `$this->directoraNorte` y `$this->directoraSur`
 * (supervisión de cada CSS); `$admNorte` (adm_usuarios de la Junta Norte, de la que cuelga el
 * CSS Norte) y `$admVallecas` (adm_usuarios de otra Junta).
 */
class ContrasenaOlvidadaTest extends TestCase
{
    use RefreshDatabase;
    use TipoCentroTestSetup;

    private User $tsNorte;

    private User $admNorte;

    private User $admVallecas;

    /**
     * Escenario de Tipo de Centro con administración de usuarios en dos juntas.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarTipoCentro();
        RateLimiter::clear('contrasena-olvidada:127.0.0.1');

        $this->tsNorte = $this->usuario('ts.olvido', 'intervencion', $this->uoCssNorte);
        $this->admNorte = $this->usuario('adm.norte', 'adm_usuarios', $this->uoJuntaNorte);
        $this->admVallecas = $this->usuario('adm.vallecas', 'adm_usuarios', $this->uoJuntaVallecas);
    }

    #[Test]
    public function el_enlace_del_login_lleva_al_formulario(): void
    {
        $this->get(route('login'))->assertSee(route('contrasena.olvidada'), false);
        $this->get(route('contrasena.olvidada'))->assertOk()->assertSee('Avisar a mi supervisor/a');
    }

    #[Test]
    public function avisa_a_la_supervision_de_su_centro_y_a_la_administracion_de_su_ambito(): void
    {
        $this->post(route('contrasena.olvidada.enviar'), ['email' => 'TS.Olvido@vida360.test'])
            ->assertRedirect(route('contrasena.olvidada'))
            ->assertSessionHas('contrasena-olvidada-enviada');

        $avisados = $this->avisadosDe($this->tsNorte);
        $this->assertEqualsCanonicalizing([$this->directoraNorte->id, $this->admNorte->id], $avisados);
        $this->assertNotContains($this->directoraSur->id, $avisados, 'Ni la supervisión de otro centro…');
        $this->assertNotContains($this->admVallecas->id, $avisados, '…ni la administración de otro ámbito.');
        $this->assertNotContains($this->tsNorte->id, $avisados);

        $alerta = Alerta::where('origen_id', $this->tsNorte->id)->first();
        $this->assertSame(AvisoContrasenaOlvidada::TITULO, $alerta->titulo);
        $this->assertStringContainsString('ts.olvido@vida360.test', $alerta->cuerpo);
        $this->assertStringContainsString('Comprueba en persona', $alerta->cuerpo);
    }

    #[Test]
    public function la_respuesta_es_la_misma_aunque_la_cuenta_no_exista_o_este_desactivada(): void
    {
        $existente = $this->post(route('contrasena.olvidada.enviar'), ['email' => 'ts.olvido@vida360.test']);
        $inexistente = $this->post(route('contrasena.olvidada.enviar'), ['email' => 'nadie@vida360.test']);

        $this->tsNorte->forceFill(['desactivado_en' => now()])->save();
        Alerta::query()->delete();
        $desactivada = $this->post(route('contrasena.olvidada.enviar'), ['email' => 'ts.olvido@vida360.test']);

        foreach ([$existente, $inexistente, $desactivada] as $respuesta) {
            $respuesta->assertRedirect(route('contrasena.olvidada'))->assertSessionHas('contrasena-olvidada-enviada')->assertSessionHasNoErrors();
        }
        $this->assertSame(0, Alerta::count(), 'Una cuenta desactivada no genera aviso.');
    }

    #[Test]
    public function no_repite_el_aviso_de_la_misma_cuenta_en_una_hora_y_limita_los_intentos(): void
    {
        $servicio = app(AvisoContrasenaOlvidada::class);
        $this->assertSame(2, $servicio->avisar('ts.olvido@vida360.test'));
        $this->assertSame(0, $servicio->avisar('ts.olvido@vida360.test'));

        $this->travel(61)->minutes();
        $this->assertSame(2, $servicio->avisar('ts.olvido@vida360.test'));

        // Por IP: el sexto intento en una hora se rechaza sin avisar
        foreach (range(1, 5) as $i) {
            $this->post(route('contrasena.olvidada.enviar'), ['email' => "nadie{$i}@vida360.test"])->assertSessionHasNoErrors();
        }
        $this->post(route('contrasena.olvidada.enviar'), ['email' => 'ts.olvido@vida360.test'])->assertSessionHasErrors('email');
    }

    #[Test]
    public function sin_supervision_ni_administracion_de_su_ambito_avisa_a_adm_sistema(): void
    {
        $aislada = $this->usuario('sin.nadie', 'intervencion', $this->uoCiamUsera);
        $sistema = $this->usuario('sistema', 'adm_sistema', $this->uoArea);

        app(AvisoContrasenaOlvidada::class)->avisar('sin.nadie@vida360.test');

        $avisados = $this->avisadosDe($aislada);
        $this->assertContains($sistema->id, $avisados);
    }

    /**
     * Ids de los usuarios avisados de la contraseña olvidada de una cuenta.
     *
     * @param User $usuario Cuenta bloqueada.
     *
     * @return list<int>
     */
    private function avisadosDe(User $usuario): array
    {
        $alertas = Alerta::where('origen_type', User::class)->where('origen_id', $usuario->id)->pluck('id');

        return AlertaDestinatario::whereIn('alerta_id', $alertas)->pluck('usuario_id')->map(fn ($id) => (int) $id)->values()->all();
    }
}
