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
    /**
     * Redirige al inicio si el usuario ya completó el onboarding.
     *
     * @param Request $request Petición entrante.
     * @param Closure $next Siguiente middleware.
     * @return Response
     */
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()->primer_acceso) {
            return redirect()->route('inicio');
        }

        return $next($request);
    }
}
