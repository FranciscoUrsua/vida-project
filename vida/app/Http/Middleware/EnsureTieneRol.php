<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirige a /sin-rol si el usuario autenticado no tiene ningún rol asignado.
 *
 * Solo cubre el caso de cero roles — cuando el usuario tiene un rol pero no
 * el adecuado para la ruta, el middleware role:* de Spatie sigue devolviendo 403.
 *
 * No debe aplicarse a la propia ruta /sin-rol para evitar bucles.
 */
class EnsureTieneRol
{
    /**
     * @param Closure(Request): Response $next
     * @param Request $request Peticion entrante.
     *
     * @return Response Respuesta siguiente o redireccion a sin-rol.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Solo afecta a usuarios asistenciales (con profesional_id)
        if (Auth::check()
            && Auth::user()->profesional_id !== null
            && Auth::user()->roles()->count() === 0
        ) {
            return redirect()->route('sin-rol');
        }

        return $next($request);
    }
}
