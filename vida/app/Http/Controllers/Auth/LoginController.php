<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Superficie\DestinoInicial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Gestiona el acceso y la salida de la aplicación operacional.
 * El backoffice (/admin) tiene su propia autenticación Filament — no usar este controlador allí.
 * El enlace directo pendiente solo vale para el mismo usuario que lo dejó (R4.8).
 */
class LoginController extends Controller
{
    /** Cookie (cifrada) con el id del último usuario que entró en este navegador. */
    public const COOKIE_ULTIMO_USUARIO = 'vida_ultimo_usuario';

    /** Un año: basta con que dure más que cualquier sesión. */
    private const DURACION_COOKIE_MINUTOS = 60 * 24 * 365;

    /**
     * Muestra el formulario de login.
     */
    public function mostrar(): View
    {
        return view('auth.login');
    }

    /**
     * Procesa el intento de autenticación.
     *
     * @param Request $request Petición entrante.
     *
     * @throws ValidationException
     */
    public function autenticar(Request $request): RedirectResponse
    {
        $credenciales = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credenciales, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $request->session()->regenerate();
        $this->descartarEnlaceDeOtroUsuario($request);

        if (Auth::user()->primer_acceso) {
            return redirect()->route('onboarding');
        }

        // Un enlace directo (alerta, correo) manda sobre la página de inicio (R4.8). Sin él,
        // el único resolutor, que también lleva al usuario sin roles a su página informativa.
        return redirect()->intended(DestinoInicial::para(Auth::user()));
    }

    /**
     * Descarta el enlace directo pendiente si lo dejó otro usuario en este navegador, y
     * recuerda quién acaba de entrar.
     *
     * Cuando una sesión caduca con una página abierta, esa página queda como enlace directo
     * (`url.intended`) para el siguiente login, sea quien sea: en un equipo compartido, otro
     * profesional aterrizaba en la ficha de una persona que no le corresponde (staging, 25 nov
     * 2026). La cookie del último usuario sobrevive a la sesión; si no existe (primer login en
     * el navegador), el enlace se respeta, como un enlace de una alerta o un correo.
     *
     * @param Request $request Petición del login, ya autenticada.
     *
     * @return void
     */
    private function descartarEnlaceDeOtroUsuario(Request $request): void
    {
        $anterior = $request->cookie(self::COOKIE_ULTIMO_USUARIO);
        $usuarioId = (string) Auth::id();

        if (is_string($anterior) && $anterior !== $usuarioId) {
            $request->session()->forget('url.intended');
        }

        Cookie::queue(self::COOKIE_ULTIMO_USUARIO, $usuarioId, self::DURACION_COOKIE_MINUTOS);
    }

    /**
     * Cierra la sesión activa.
     *
     * @param Request $request Petición entrante.
     */
    public function cerrarSesion(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
