<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AvisoContrasenaOlvidada;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * «¿Olvidaste tu contraseña?» de la pantalla de acceso (27 nov 2026): sin correo, avisa a la
 * supervisión del centro y a la administración de usuarios (`AvisoContrasenaOlvidada`).
 *
 * La respuesta es la misma exista o no la cuenta, para no revelar qué correos tienen acceso, y
 * los intentos se limitan por IP.
 */
class ContrasenaOlvidadaController extends Controller
{
    /** Intentos por IP y hora. */
    private const INTENTOS_POR_HORA = 5;

    /**
     * Muestra el formulario.
     *
     * @return View
     */
    public function mostrar(): View
    {
        return view('auth.contrasena-olvidada');
    }

    /**
     * Avisa a quien puede ayudar y responde siempre lo mismo.
     *
     * @param Request $request Petición con el correo.
     * @param AvisoContrasenaOlvidada $aviso Servicio de aviso.
     *
     * @return RedirectResponse
     *
     * @throws ValidationException Si el correo no es válido o se superan los intentos.
     */
    public function enviar(Request $request, AvisoContrasenaOlvidada $aviso): RedirectResponse
    {
        $datos = $request->validate(['email' => ['required', 'email', 'max:255']], [
            'email.required' => 'Escribe tu correo electrónico.',
            'email.email' => 'Escribe un correo electrónico válido.',
        ]);

        $clave = 'contrasena-olvidada:'.$request->ip();
        if (RateLimiter::tooManyAttempts($clave, self::INTENTOS_POR_HORA)) {
            throw ValidationException::withMessages(['email' => 'Has hecho demasiados intentos. Vuelve a probar más tarde o habla con tu supervisor/a.']);
        }
        RateLimiter::hit($clave, 3600);

        $aviso->avisar($datos['email']);

        return redirect()->route('contrasena.olvidada')->with('contrasena-olvidada-enviada', true);
    }
}
