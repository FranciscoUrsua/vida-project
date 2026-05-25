<?php

namespace Modules\Intervencion\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Modules\Intervencion\Models\PlanDeIntervencion;

/**
 * Servicio de dominio para el Plan de Intervención.
 *
 * Centraliza las operaciones de escritura sobre PlanDeIntervencion garantizando
 * que todas las mutaciones pasen por el par (GlobalScope + Policy).
 *
 * El versionado del plan es no destructivo: crearNuevaVersion() genera un
 * nuevo registro. El servicio expone los métodos básicos de escritura;
 * la lógica de negocio compleja (firma, revisión) pertenece al modelo.
 *
 * @see \Modules\Intervencion\Policies\PlanDeIntervencionPolicy
 * @see \Modules\Intervencion\Models\PlanDeIntervencion::crearNuevaVersion()
 */
class PlanDeIntervencionService
{
    /**
     * Crea un nuevo Plan de Intervención.
     *
     * @param array<string, mixed> $datos Datos del plan a crear
     * @return PlanDeIntervencion
     * @throws AuthorizationException Si el usuario no tiene permiso de crear planes
     */
    public function crear(array $datos): PlanDeIntervencion
    {
        Gate::authorize('create', PlanDeIntervencion::class);

        // TODO: mover la lógica de negocio aquí (validación de historia activa, unicidad de plan activo, etc.)
        return PlanDeIntervencion::create($datos);
    }

    /**
     * Actualiza un Plan de Intervención existente.
     *
     * El GlobalScope filtra por ámbito de UO vía Historia Social.
     * La Policy verifica el permiso de edición y el ámbito de UO.
     *
     * @param int                  $id    ID del plan
     * @param array<string, mixed> $datos Campos a actualizar
     * @return PlanDeIntervencion
     * @throws AuthorizationException Si el usuario no tiene permiso de editar
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Si el plan no existe o no está en el ámbito del usuario
     */
    public function actualizar(int $id, array $datos): PlanDeIntervencion
    {
        $plan = PlanDeIntervencion::findOrFail($id); // GlobalScope ya filtra
        Gate::authorize('update', $plan);             // Policy verifica

        // TODO: mover la lógica de negocio aquí (no se puede cambiar historia_id, validar estado, etc.)
        $plan->update($datos);

        return $plan;
    }

    /**
     * Elimina (baja lógica) un Plan de Intervención.
     *
     * Solo planes en estado borrador deberían poder eliminarse; la lógica
     * de validación de estado debe implementarse aquí.
     *
     * @param int $id ID del plan
     * @return void
     * @throws AuthorizationException Si el usuario no tiene permiso de eliminar
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Si el plan no existe o no está en el ámbito del usuario
     */
    public function eliminar(int $id): void
    {
        $plan = PlanDeIntervencion::findOrFail($id); // GlobalScope ya filtra
        Gate::authorize('delete', $plan);             // Policy verifica

        // TODO: mover la lógica de negocio aquí (verificar estado borrador, desasociar apuntes, etc.)
        $plan->delete();
    }
}
