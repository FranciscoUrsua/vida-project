<?php

namespace App\Http\Middleware;

use App\Enums\AccionAuditEnum;
use App\Models\Ciudadano;
use App\Models\Scopes\AmbitoUoScope;
use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Red de seguridad de segunda línea para auditoría de accesos a ciudadanos.
 *
 * Registra un acceso 'ver' cuando se accede a una ruta con parámetro
 * {ciudadano}. Actúa como fallback por si el componente no llamó
 * explícitamente a AuditService::registrarAcceso(). Un acceso duplicado
 * (middleware + componente) es un bug menor; uno no registrado es una
 * brecha de accountability.
 *
 * El middleware no sustituye la llamada explícita desde el componente —
 * complementa. Los componentes deben seguir llamando a registrarAcceso()
 * para incluir contexto adicional (modelo concreto consultado, etc.).
 *
 * @see docs/modulo-auditoria.md §3.4
 */
class AuditarAccesoCiudadano
{
    /**
     * Inyecta el servicio de auditoría.
     *
     * @param AuditService $service Servicio de auditoría.
     */
    public function __construct(private readonly AuditService $service) {}

    /**
     * Procesa la petición y registra el acceso al ciudadano si corresponde.
     *
     * @param Request $request Petición entrante.
     * @param Closure $next Siguiente middleware.
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            Auth::check()
            && $response->isSuccessful()
            && ($ciudadanoId = $request->route('ciudadano'))
            && is_numeric($ciudadanoId)
        ) {
            $ciudadano = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
                ->find((int) $ciudadanoId);

            if ($ciudadano) {
                $this->service->registrarAcceso(
                    user: Auth::user(),
                    modelo: $ciudadano,
                    accion: AccionAuditEnum::Ver,
                );
            }
        }

        return $response;
    }
}
