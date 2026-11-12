<?php

namespace Modules\Intervencion\Services;

use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Policies\PlanDeIntervencionPolicy;

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
 * @see PlanDeIntervencionPolicy
 * @see PlanDeIntervencion::crearNuevaVersion()
 */
class PlanDeIntervencionService
{
    /**
     * Crea un nuevo Plan de Intervención.
     *
     * @param array<string, mixed> $datos Datos del plan a crear
     * @param Centro|null $centro Centro en que se abre: su tipo debe ofrecer el tipo de plan.
     *
     * @throws AuthorizationException Si el usuario no tiene permiso de crear planes
     * @throws DomainException Si el centro no ofrece el tipo de plan.
     *
     * @return PlanDeIntervencion
     */
    public function crear(array $datos, ?Centro $centro = null): PlanDeIntervencion
    {
        Gate::authorize('create', PlanDeIntervencion::class);

        if (isset($datos['tipo_plan_id'])) {
            $this->exigirTipoPlanDelCentro((int) $datos['tipo_plan_id'], $centro);
        }

        // TODO: mover la lógica de negocio aquí (validación de historia activa, unicidad de plan activo, etc.)
        return PlanDeIntervencion::create($datos);
    }

    /**
     * Rechaza un tipo de plan que el centro no ofrece (docs/modulo-tipo-centro.md §6.2).
     *
     * Sin centro no se comprueba: la atención sin centro la decide RN-15.
     *
     * @param int $tipoPlanId Tipo de plan elegido.
     * @param Centro|null $centro Centro en que se abre el plan.
     *
     * @throws DomainException
     *
     * @return void
     */
    public function exigirTipoPlanDelCentro(int $tipoPlanId, ?Centro $centro): void
    {
        if ($centro === null) {
            return;
        }

        if (! app(ConfiguracionEfectivaCentro::class)->tiposPlan($centro)->contains('id', $tipoPlanId)) {
            throw new DomainException("Ese tipo de plan no se ofrece en {$centro->nombre}.");
        }
    }

    /**
     * Actualiza un Plan de Intervención existente.
     *
     * El GlobalScope filtra por ámbito de UO vía Historia Social.
     * La Policy verifica el permiso de edición y el ámbito de UO.
     *
     * @param int $id ID del plan
     * @param array<string, mixed> $datos Campos a actualizar
     *
     * @throws AuthorizationException Si el usuario no tiene permiso de editar
     * @throws ModelNotFoundException Si el plan no existe o no está en el ámbito del usuario
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
     *
     * @throws AuthorizationException Si el usuario no tiene permiso de eliminar
     * @throws ModelNotFoundException Si el plan no existe o no está en el ámbito del usuario
     */
    public function eliminar(int $id): void
    {
        $plan = PlanDeIntervencion::findOrFail($id); // GlobalScope ya filtra
        Gate::authorize('delete', $plan);             // Policy verifica

        // TODO: mover la lógica de negocio aquí (verificar estado borrador, desasociar apuntes, etc.)
        $plan->delete();
    }
}
