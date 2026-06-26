<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
     * Marca el onboarding como completado y redirige al destino según rol.
     *
     * @param Request $request Petición entrante.
     */
    public function completar(Request $request): RedirectResponse
    {
        $usuario = Auth::user();
        $usuario->update(['primer_acceso' => false]);

        if ($usuario->hasAnyRole(['adm_sistema', 'adm_usuarios'])) {
            return redirect('/admin');
        }

        if ($usuario->hasRole('supervision')) {
            return redirect()->route('supervision.inicio');
        }

        if ($usuario->hasRole('intervencion')) {
            return redirect()->route('intervencion.agenda.index');
        }

        if ($usuario->roles()->count() === 0) {
            return redirect()->route('sin-rol');
        }

        return redirect()->route('inicio');
    }
}
