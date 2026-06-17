<?php

namespace Modules\Intervencion\Services;

use App\Models\HistoriaSocial;
use App\Models\Scopes\AmbitoUoScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Modules\Usuarios\Policies\HistoriaSocialPolicy;

/**
 * Servicio de dominio para la Historia Social.
 *
 * Centraliza las operaciones de escritura sobre HistoriaSocial garantizando que
 * todas las mutaciones pasen por el par (GlobalScope + Policy) definido en la
 * estrategia de seguridad en profundidad.
 *
 * Patrón de cada método de escritura:
 *   1. Recuperar el registro (el GlobalScope ya filtra por ámbito de UO).
 *   2. Verificar la Policy (Gate::authorize).
 *   3. Ejecutar la lógica de negocio.
 *
 * @see AmbitoUoScope
 * @see HistoriaSocialPolicy
 */
class HistoriaSocialService
{
    /**
     * Abre (crea) una nueva Historia Social.
     *
     * La Policy verifica que el usuario tiene historia.crear y pertenece
     * al ámbito de UO de la historia a crear.
     *
     * @param array<string, mixed> $datos Datos de la nueva Historia Social
     *
     * @return HistoriaSocial
     *
     * @throws AuthorizationException Si el usuario no tiene permiso de crear
     */
    public function crear(array $datos): HistoriaSocial
    {
        Gate::authorize('create', HistoriaSocial::class);

        // TODO: mover la lógica de negocio aquí (validación de estado, unicidad de historia activa por ciudadano, etc.)
        return HistoriaSocial::create($datos);
    }

    /**
     * Actualiza una Historia Social existente.
     *
     * El GlobalScope garantiza que findOrFail solo encuentra la Historia
     * si el usuario tiene acceso al ámbito de UO.
     * La Policy verifica adicionalmente el permiso de edición.
     *
     * @param int $id ID de la Historia Social
     * @param array<string, mixed> $datos Campos a actualizar
     *
     * @return HistoriaSocial
     *
     * @throws AuthorizationException Si el usuario no tiene permiso de editar
     * @throws ModelNotFoundException Si la historia no existe o no está en el ámbito del usuario
     */
    public function actualizar(int $id, array $datos): HistoriaSocial
    {
        $historia = HistoriaSocial::findOrFail($id); // GlobalScope ya filtra por ámbito
        Gate::authorize('update', $historia);         // Policy verifica permiso atómico + UO

        // TODO: mover la lógica de negocio aquí (registro de cambios, versionado, notificaciones)
        $historia->update($datos);

        return $historia;
    }

    /**
     * Elimina (baja lógica) una Historia Social.
     *
     * Solo aplica soft delete. El pasado es inmutable (principio 4.2):
     * los registros históricos no se destruyen.
     *
     * @param int $id ID de la Historia Social
     *
     * @return void
     *
     * @throws AuthorizationException Si el usuario no tiene permiso de eliminar
     * @throws ModelNotFoundException Si la historia no existe o no está en el ámbito del usuario
     */
    public function eliminar(int $id): void
    {
        $historia = HistoriaSocial::findOrFail($id); // GlobalScope ya filtra por ámbito
        Gate::authorize('delete', $historia);         // Policy verifica permiso atómico + UO

        // TODO: mover la lógica de negocio aquí (verificar que no haya planes activos, notificaciones)
        $historia->delete();
    }
}
