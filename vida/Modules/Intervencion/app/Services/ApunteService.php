<?php

namespace Modules\Intervencion\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Modules\Intervencion\Models\Apunte;

/**
 * Servicio de dominio para el Apunte del módulo Intervención.
 *
 * Centraliza las operaciones de escritura sobre Apunte garantizando que
 * todas las mutaciones pasen por el par (GlobalScope + Policy).
 *
 * Regla crítica de dominio: las anotaciones privadas son inmutables desde
 * el punto de vista del acceso ajeno — la Policy aplica la restricción de autor
 * con precedencia absoluta.
 *
 * @see \Modules\Intervencion\Policies\ApuntePolicy
 */
class ApunteService
{
    /**
     * Crea un nuevo apunte.
     *
     * @param array<string, mixed> $datos Datos del apunte a crear
     * @return Apunte
     * @throws AuthorizationException Si el usuario no tiene permiso de crear apuntes
     */
    public function crear(array $datos): Apunte
    {
        Gate::authorize('create', Apunte::class);

        // TODO: mover la lógica de negocio aquí (validación del plan asociado, tipo de apunte, etc.)
        return Apunte::create($datos);
    }

    /**
     * Actualiza un apunte existente.
     *
     * El GlobalScope filtra automáticamente por ámbito de UO + autor para privados.
     * La Policy verifica el permiso y aplica la regla de autor para privados.
     *
     * @param int                  $id    ID del apunte
     * @param array<string, mixed> $datos Campos a actualizar
     * @return Apunte
     * @throws AuthorizationException Si el usuario no tiene permiso de editar
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Si el apunte no existe o no está en el ámbito del usuario
     */
    public function actualizar(int $id, array $datos): Apunte
    {
        $apunte = Apunte::findOrFail($id); // GlobalScope ya filtra
        Gate::authorize('update', $apunte); // Policy verifica

        // TODO: mover la lógica de negocio aquí (no se puede cambiar plan_id, validar visibilidad, etc.)
        $apunte->update($datos);

        return $apunte;
    }

    /**
     * Elimina un apunte.
     *
     * Solo los apuntes privados pueden eliminarse (son notas personales).
     * Los apuntes de visibilidad profesionales o ciudadano son registro permanente.
     *
     * @param int $id ID del apunte
     * @return void
     * @throws AuthorizationException Si el usuario no tiene permiso de eliminar
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Si el apunte no existe o no está en el ámbito del usuario
     */
    public function eliminar(int $id): void
    {
        $apunte = Apunte::findOrFail($id); // GlobalScope ya filtra
        Gate::authorize('delete', $apunte); // Policy verifica

        // TODO: mover la lógica de negocio aquí (auditoría de eliminación, etc.)
        $apunte->delete();
    }
}
