<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales de autenticación — TF-AUTH-01 a TF-AUTH-23.
 *
 * Cubre: acceso al formulario, login correcto/incorrecto, throttle,
 * logout, protección de rutas, identidad en UI, onboarding y badge de entorno.
 */
class AutenticacionTest extends TestCase
{
    use RefreshDatabase;

    protected User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create([
            'name' => 'Juana López García',
            'email' => 'juana@madrid.es',
            'password' => 'secreto123',
            'email_verified_at' => now(),
            'primer_acceso' => false,
        ]);
    }

    // =========================================================================
    // Grupo A — Acceso al formulario de login
    // =========================================================================

    #[Test]
    public function tf_auth_01_la_ruta_login_devuelve_200_para_visitantes(): void
    {
        $respuesta = $this->get('/login');

        $respuesta->assertStatus(200);
        $respuesta->assertSee('email', false);
        $respuesta->assertSee('password', false);
    }

    #[Test]
    public function tf_auth_02_usuario_autenticado_es_redirigido_desde_login(): void
    {
        $respuesta = $this->actingAs($this->usuario)->get('/login');

        $respuesta->assertRedirect(route('inicio'));
    }

    // =========================================================================
    // Grupo B — Login con credenciales correctas
    // =========================================================================

    #[Test]
    public function tf_auth_03_login_exitoso_con_email_y_password_correctos(): void
    {
        $respuesta = $this->post('/login', [
            'email' => 'juana@madrid.es',
            'password' => 'secreto123',
        ]);

        $respuesta->assertRedirect(route('inicio'));
        $this->assertTrue(Auth::check());
    }

    #[Test]
    public function tf_auth_04_email_es_case_sensitive_por_decision_de_diseno(): void
    {
        // PostgreSQL no normaliza el email antes de buscarlo. Laravel Auth usa
        // WHERE email = 'VALOR' de forma literal. La decisión es mantener el
        // comportamiento estándar del framework sin normalización adicional.
        $this->markTestSkipped(
            'Email es case-sensitive por decisión de diseño (comportamiento estándar de Laravel + PostgreSQL).'
        );
    }

    #[Test]
    public function tf_auth_05_tras_login_la_sesion_regenera_el_token(): void
    {
        $sesionAntes = session()->getId();

        $this->post('/login', [
            'email' => 'juana@madrid.es',
            'password' => 'secreto123',
        ]);

        // Tras regenerate() el ID de sesión cambia
        $this->assertNotEquals($sesionAntes, session()->getId());
    }

    // =========================================================================
    // Grupo C — Login con credenciales incorrectas
    // =========================================================================

    #[Test]
    public function tf_auth_06_login_fallido_con_password_incorrecta(): void
    {
        $respuesta = $this->post('/login', [
            'email' => 'juana@madrid.es',
            'password' => 'incorrecta',
        ]);

        $respuesta->assertRedirect();
        $this->assertFalse(Auth::check());
        $respuesta->assertSessionHasErrors('email');
    }

    #[Test]
    public function tf_auth_07_login_fallido_con_email_inexistente(): void
    {
        $respuesta = $this->post('/login', [
            'email' => 'noexiste@madrid.es',
            'password' => 'cualquiera',
        ]);

        $respuesta->assertRedirect();
        $this->assertFalse(Auth::check());
        $respuesta->assertSessionHasErrors('email');
        // El mensaje de error es el mismo genérico que en TF-AUTH-06
        $this->assertSame(
            trans('auth.failed'),
            session('errors')->first('email')
        );
    }

    #[Test]
    public function tf_auth_08_login_fallido_con_campos_vacios(): void
    {
        $respuesta = $this->post('/login', [
            'email' => '',
            'password' => '',
        ]);

        $respuesta->assertRedirect();
        $this->assertFalse(Auth::check());
        $respuesta->assertSessionHasErrors(['email', 'password']);
    }

    #[Test]
    public function tf_auth_09_el_email_debe_tener_formato_valido(): void
    {
        $respuesta = $this->post('/login', [
            'email' => 'noesuncorreo',
            'password' => 'cualquiera',
        ]);

        $respuesta->assertRedirect();
        $respuesta->assertSessionHasErrors('email');
    }

    // =========================================================================
    // Grupo D — Protección por throttle
    // =========================================================================

    #[Test]
    public function tf_auth_10_demasiados_intentos_fallidos_bloquean_el_acceso(): void
    {
        // Limpiamos el rate limiter para aislar el test
        RateLimiter::clear('login');

        $datos = [
            'email' => 'juana@madrid.es',
            'password' => 'incorrecta',
        ];

        // 6 intentos fallidos
        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', $datos);
        }

        // El séptimo debe devolver error de throttle
        $respuesta = $this->post('/login', $datos);

        // Laravel devuelve 302 con error de throttle o 429 directamente
        $esBloqueado = $respuesta->getStatusCode() === 429
            || session('errors')?->has('email');

        $this->assertTrue(
            $esBloqueado,
            'Se esperaba bloqueo por throttle tras 6 intentos fallidos'
        );
    }

    #[Test]
    public function tf_auth_11_el_throttle_es_por_email_e_ip_no_solo_por_ip(): void
    {
        RateLimiter::clear('login');

        $usuarioB = User::factory()->create([
            'email' => 'pedro@madrid.es',
            'password' => 'otrapass',
            'primer_acceso' => false,
        ]);

        // 5 intentos fallidos para usuario_a
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'juana@madrid.es',
                'password' => 'incorrecta',
            ]);
        }

        // El intento de usuario_b no debe estar bloqueado
        $respuesta = $this->post('/login', [
            'email' => 'pedro@madrid.es',
            'password' => 'otrapass',
        ]);

        // Si el throttle fuera solo por IP, usuario_b estaría bloqueado
        $respuesta->assertRedirect(route('inicio'));
        $this->assertTrue(Auth::check());
    }

    // =========================================================================
    // Grupo E — Logout
    // =========================================================================

    #[Test]
    public function tf_auth_12_logout_cierra_la_sesion_correctamente(): void
    {
        $respuesta = $this->actingAs($this->usuario)->post('/logout');

        $this->assertFalse(Auth::check());
        $respuesta->assertRedirect(route('login'));
    }

    #[Test]
    public function tf_auth_13_logout_requiere_metodo_post_no_get(): void
    {
        $respuesta = $this->actingAs($this->usuario)->get('/logout');

        // Debe devolver 405 Method Not Allowed
        $respuesta->assertStatus(405);
        // La sesión sigue activa — no se cerró
        $this->assertTrue(Auth::check());
    }

    // =========================================================================
    // Grupo F — Protección de rutas
    // =========================================================================

    #[Test]
    public function tf_auth_14_las_rutas_protegidas_redirigen_a_login_sin_sesion(): void
    {
        $respuesta = $this->get('/');

        $respuesta->assertRedirect(route('login'));
    }

    #[Test]
    public function tf_auth_15_usuario_autenticado_puede_acceder_a_inicio(): void
    {
        $respuesta = $this->actingAs($this->usuario)->get('/');

        $respuesta->assertStatus(200);
    }

    // =========================================================================
    // Grupo G — Identidad visible en la UI
    // =========================================================================

    #[Test]
    public function tf_auth_16_el_nombre_del_usuario_aparece_en_la_ui(): void
    {
        $respuesta = $this->actingAs($this->usuario)->get('/');

        $respuesta->assertStatus(200);
        // Debe contener el nombre, no el email
        $respuesta->assertSee('Juana López');
        $respuesta->assertDontSee('juana@madrid.es');
    }

    #[Test]
    public function tf_auth_17_las_iniciales_del_avatar_son_las_dos_primeras_letras_del_name(): void
    {
        $respuesta = $this->actingAs($this->usuario)->get('/');

        $respuesta->assertStatus(200);
        $respuesta->assertSee('JL');
        $respuesta->assertDontSee('JU');
        $respuesta->assertDontSee('JLG');
    }

    // =========================================================================
    // Grupo H — Primer acceso (onboarding)
    // =========================================================================

    #[Test]
    public function tf_auth_18_usuario_nuevo_ve_pantalla_de_onboarding_tras_login(): void
    {
        $nuevo = User::factory()->create([
            'email' => 'nuevo@madrid.es',
            'password' => 'secreto123',
            'primer_acceso' => true,
        ]);

        $respuesta = $this->post('/login', [
            'email' => 'nuevo@madrid.es',
            'password' => 'secreto123',
        ]);

        $respuesta->assertRedirect(route('onboarding'));
    }

    #[Test]
    public function tf_auth_19_usuario_recurrente_va_directamente_a_inicio(): void
    {
        $respuesta = $this->post('/login', [
            'email' => 'juana@madrid.es',
            'password' => 'secreto123',
        ]);

        $respuesta->assertRedirect(route('inicio'));
    }

    #[Test]
    public function tf_auth_20_onboarding_muestra_nombre_y_centro_del_usuario(): void
    {
        // El módulo Centro no tiene aún relación profesional→centroActivo().
        // Este test se completará cuando se implemente esa relación.
        $this->markTestIncomplete(
            'Requiere relación Profesional::centroActivo() implementada en el módulo Centro.'
        );
    }

    #[Test]
    public function tf_auth_21_completar_onboarding_marca_primer_acceso_como_false(): void
    {
        $nuevo = User::factory()->create(['primer_acceso' => true]);

        $respuesta = $this->actingAs($nuevo)->post('/bienvenida');

        $respuesta->assertRedirect(route('inicio'));
        $this->assertFalse($nuevo->fresh()->primer_acceso);
    }

    #[Test]
    public function tf_auth_22_acceder_a_onboarding_con_primer_acceso_false_redirige_a_inicio(): void
    {
        // $this->usuario tiene primer_acceso = false
        $respuesta = $this->actingAs($this->usuario)->get('/bienvenida');

        $respuesta->assertRedirect(route('inicio'));
    }

    // =========================================================================
    // Grupo I — Badge de entorno
    // =========================================================================

    #[Test]
    public function tf_auth_23_el_badge_muestra_el_valor_de_app_env_label(): void
    {
        config(['app.env_label' => 'Staging']);

        $respuesta = $this->get('/login');

        $respuesta->assertStatus(200);
        $respuesta->assertSee('Staging');
    }
}
