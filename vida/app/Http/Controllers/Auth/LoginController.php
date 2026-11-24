<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Superficie\DestinoInicial;
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

        // Un enlace directo (alerta, correo) manda sobre la página de inicio (R4.8). Sin él,
        // el único resolutor, que también lleva al usuario sin roles a su página informativa.
        return redirect()->intended(DestinoInicial::para(Auth::user()));
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
