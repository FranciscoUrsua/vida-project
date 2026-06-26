<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Gestiona el acceso y la salida de la aplicación operacional.
 * El backoffice (/admin) tiene su propia autenticación Filament — no usar este controlador allí.
 */
class LoginController extends Controller
{
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

        if (Auth::user()->primer_acceso) {
            return redirect()->route('onboarding');
        }

        if (Auth::user()->profesional_id !== null && Auth::user()->roles()->count() === 0) {
            return redirect()->route('sin-rol');
        }

        return redirect($this->destino());
    }

    /**
     * Determina la ruta de destino tras el login según el rol del usuario.
     *
     * - adm_sistema, adm_usuarios → /admin (Filament backoffice)
     * - supervision → /supervision/inicio (superficie operativa de supervisión)
     * - intervencion → agenda operativa
     * - Cualquier otro → pantalla de inicio genérica
     */
    private function destino(): string
    {
        $usuario = Auth::user();

        if ($usuario->hasAnyRole(['adm_sistema', 'adm_usuarios'])) {
            return '/admin';
        }

        if ($usuario->hasRole('supervision')) {
            return route('supervision.inicio');
        }

        if ($usuario->hasRole('intervencion')) {
            return route('intervencion.agenda.index');
        }

        if ($usuario->roles()->count() === 0) {
            return route('sin-rol');
        }

        return route('inicio');
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
