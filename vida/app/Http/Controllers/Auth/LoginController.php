<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Gestiona el acceso y la salida de la aplicación operacional.
 * El backoffice (/admin) tiene su propia autenticación Filament — no usar este controlador allí.
 */
class LoginController extends Controller
{
    /**
     * Muestra el formulario de login.
     */
    public function mostrar()
    {
        return view('auth.login');
    }

    /**
     * Procesa el intento de autenticación.
     *
     * @throws ValidationException
     */
    public function autenticar(Request $request)
    {
        $credenciales = $request->validate([
            'email'    => ['required', 'email'],
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

        // Solo redirigir a sin-rol a usuarios asistenciales (con profesional_id)
        // Los perfiles técnicos sin profesional (adm_sistema) no pasan por este flujo
        if (Auth::user()->profesional_id !== null && Auth::user()->roles()->count() === 0) {
            return redirect()->route('sin-rol');
        }

        return redirect()->intended($this->destino());
    }

    /**
     * Determina la ruta de destino tras el login según el rol del usuario.
     *
     * - Roles de backoffice (adm_sistema, supervision, adm_usuarios) → /admin
     * - Rol intervencion → agenda operativa
     * - Cualquier otro → pantalla de inicio genérica
     */
    private function destino(): string
    {
        $usuario = Auth::user();

        if ($usuario->hasAnyRole(['adm_sistema', 'supervision', 'adm_usuarios'])) {
            return '/admin';
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
     */
    public function cerrarSesion(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
