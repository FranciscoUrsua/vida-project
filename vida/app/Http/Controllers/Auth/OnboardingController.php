<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Gestiona la pantalla de primer acceso que se muestra una sola vez
 * tras la creación de la cuenta.
 */
class OnboardingController extends Controller
{
    /**
     * Muestra la pantalla de bienvenida con el contexto del usuario.
     */
    public function mostrar()
    {
        $usuario = Auth::user();
        $centro = $usuario->profesional?->centroActivo()?->nombre;

        return view('auth.onboarding', compact('usuario', 'centro'));
    }

    /**
     * Marca el onboarding como completado y redirige a inicio.
     */
    public function completar(Request $request)
    {
        Auth::user()->update(['primer_acceso' => false]);

        return redirect()->route('inicio');
    }
}
