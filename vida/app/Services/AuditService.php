<?php

namespace App\Services;

use App\Enums\AccionAuditEnum;
use App\Models\Audit;
use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio centralizado de registro de auditoría.
 *
 * Es el único punto desde el que se crea un registro en `audits`.
 * Ningún componente debe llamar directamente a Audit::create() — todo pasa
 * por este servicio para garantizar la resolución de ciudadano_id y contexto.
 *
 * Las escrituras (crear/editar/eliminar) se registran automáticamente a través
 * de AuditObserver. Las lecturas deben registrarse explícitamente llamando
 * a registrarAcceso() desde el componente Livewire o Resource de Filament.
 *
 * @see docs/modulo-auditoria.md §3.3
 * @see \App\Observers\AuditObserver
 */
class AuditService
{
    /**
     * Registra un acceso a datos de ciudadano.
     *
     * La resolución de ciudadano_id sigue este orden de prioridad:
     *   1. El parámetro $ciudadanoId si se pasa explícitamente.
     *   2. $modelo->getCiudadanoId() si el modelo usa el trait Auditable.
     *   3. null si ninguno de los anteriores está disponible.
     *
     * @param  User                      $user         Profesional que realiza la acción
     * @param  Model                     $modelo       Entidad afectada
     * @param  AccionAuditEnum|string    $accion       Tipo de acción (default: ver)
     * @param  int|null                  $ciudadanoId  FK explícita — tiene prioridad
     * @param  array<string, mixed>      $contexto     Metadatos adicionales
     * @param  array<string, mixed>|null $datosAntes   Snapshot previo (editar/eliminar)
     * @param  array<string, mixed>|null $datosDespues Snapshot posterior (crear/editar)
     *
     * @return void
     */
    public function registrarAcceso(
        User $user,
        Model $modelo,
        AccionAuditEnum|string $accion = AccionAuditEnum::Ver,
        ?int $ciudadanoId = null,
        array $contexto = [],
        ?array $datosAntes = null,
        ?array $datosDespues = null,
    ): void {
        $accionEnum = $accion instanceof AccionAuditEnum
            ? $accion
            : AccionAuditEnum::from($accion);

        $resolvedCiudadanoId = $ciudadanoId
            ?? (in_array(Auditable::class, class_uses_recursive($modelo))
                ? $modelo->getCiudadanoId()
                : null);

        $contextoCompleto = array_merge($this->contextoBase(), $contexto);

        // Usamos el query builder directamente para evitar que el AuditObserver
        // intercepte esta inserción y genere registros de auditoría recursivos.
        Audit::withoutEvents(function () use (
            $user, $modelo, $accionEnum, $resolvedCiudadanoId,
            $datosAntes, $datosDespues, $contextoCompleto
        ): void {
            Audit::create([
                'user_id'        => $user->id,
                'accion'         => $accionEnum->value,
                'auditable_type' => get_class($modelo),
                'auditable_id'   => $modelo->getKey(),
                'ciudadano_id'   => $resolvedCiudadanoId,
                'datos_antes'    => $datosAntes,
                'datos_despues'  => $datosDespues,
                'ip'             => request()?->ip(),
                'user_agent'     => request()?->userAgent(),
                'contexto'       => $contextoCompleto ?: null,
            ]);
        });
    }

    /**
     * Contexto base derivado automáticamente de la petición actual.
     *
     * @return array<string, string>
     */
    private function contextoBase(): array
    {
        $request = request();

        if (! $request) {
            return [];
        }

        $path = $request->path();

        // En contexto de consola la request es un objeto vacío sin ruta real
        if (empty($path) || $path === '/') {
            return [];
        }

        return [
            'canal' => $request->expectsJson() ? 'api' : 'web',
            'ruta'  => $path,
        ];
    }
}
