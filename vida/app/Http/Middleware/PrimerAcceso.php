<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Redirige a inicio si el usuario ya ha completado el onboarding.
 * Solo actúa sobre rutas protegidas con este middleware.
 */
class PrimerAcceso
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()->primer_acceso) {
            return redirect()->route('inicio');
        }

        return $next($request);
    }
}
