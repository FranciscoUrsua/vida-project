<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Superficie\DestinoInicial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Gestiona la pantalla de primer acceso que se muestra una sola vez
 * tras la creación de la cuenta.
 */
class OnboardingController extends Controller
{
    /**
     * Muestra la pantalla de bienvenida con el contexto del usuario.
     */
    public function mostrar(): View
    {
        $usuario = Auth::user();
        $centro = null;

        return view('auth.onboarding', compact('usuario', 'centro'));
    }

    /**
     * Marca el onboarding como completado y lleva al usuario a su página de inicio (DestinoInicial).
     *
     * @param Request $request Petición entrante.
     */
    public function completar(Request $request): RedirectResponse
    {
        $usuario = Auth::user();
        $usuario->update(['primer_acceso' => false]);

        // El primer acceso termina siempre en la página de inicio (paso 1.6), no en un
        // destino solicitado antes del login
        $request->session()->forget('url.intended');

        return redirect(DestinoInicial::para($usuario));
    }
}
