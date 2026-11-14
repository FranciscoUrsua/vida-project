<?php

namespace Modules\Intervencion\Services;

use App\Models\HistoriaSocial;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\TipoPlan;
use Modules\Intervencion\Policies\PlanDeIntervencionPolicy;
use Modules\Intervencion\Services\Asignacion\AsignacionReferenciaService;

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
     * Crea un plan de intervención aplicando las reglas de apertura de la fase 3 (F3-1, F3-2, A2.2).
     *
     * - El plan exige tipo de plan y se abre en un centro (el indicado o el centro activo
     *   del usuario) que ofrezca ese tipo.
     * - El plan principal (el PISO) solo se abre en el centro de la historia, y lo abre el
     *   profesional de referencia vigente o la supervisión de ese centro; si la historia no
     *   tiene referencia vigente, cualquier profesional adscrito al centro.
     * - Los demás planes de ASP y los especializados con entrada directa los abre cualquier
     *   profesional adscrito al centro que los ofrece; sin entrada directa, un especializado
     *   solo nace de una derivación (`DerivacionService`).
     * - Una historia no tiene dos planes vigentes del mismo tipo de plan.
     *
     * Los planes de unidad de convivencia quedan fuera de estas reglas (sin cambios en la fase 3).
     *
     * @param array<string, mixed> $datos Datos del plan; el responsable por defecto es el actor.
     * @param Centro|null $centro Centro en que se abre; null = centro activo del actor.
     * @param User|null $actor Quien abre el plan; null = usuario autenticado.
     *
     * @return PlanDeIntervencion
     *
     * @throws AuthorizationException Si el usuario no tiene permiso de crear planes
     * @throws DomainException Si alguna regla de apertura no se cumple.
     */
    public function crear(array $datos, ?Centro $centro = null, ?User $actor = null): PlanDeIntervencion
    {
        $actor ??= auth()->user();
        Gate::forUser($actor)->authorize('create', PlanDeIntervencion::class);

        if (! empty($datos['unidad_convivencia_id'])) {
            if (isset($datos['tipo_plan_id'])) {
                $this->exigirTipoPlanDelCentro((int) $datos['tipo_plan_id'], $centro);
            }

            return PlanDeIntervencion::create($datos);
        }

        if (empty($datos['tipo_plan_id'])) {
            throw new DomainException('Elige el tipo de plan.');
        }

        $centrosUsuario = app(CentroDeUsuario::class);
        $centro ??= $centrosUsuario->centroActivo($actor);

        if ($centro === null) {
            throw new DomainException('Para abrir un plan tienes que estar trabajando en un centro.');
        }

        $this->exigirTipoPlanDelCentro((int) $datos['tipo_plan_id'], $centro);

        $tipoPlan = TipoPlan::findOrFail($datos['tipo_plan_id']);
        $historia = HistoriaSocial::withoutGlobalScopes()->findOrFail($datos['historia_id']);

        if ($tipoPlan->es_principal) {
            $this->exigirAperturaDelPrincipal($historia, $centro, $actor);
        } else {
            if ($tipoPlan->ambito === 'especializado' && ! $tipoPlan->admite_entrada_directa) {
                throw new DomainException("Un {$tipoPlan->nombre} solo nace de una derivación desde el plan principal.");
            }

            if (! $centrosUsuario->adscritoA($actor, $centro)) {
                throw new DomainException("Para abrir un {$tipoPlan->nombre} tienes que estar adscrito a {$centro->nombre}.");
            }
        }

        $this->exigirSinPlanVigenteDelTipo($historia->id, $tipoPlan);

        unset($datos['tipo']); // lo deriva el modelo del ámbito del tipo de plan

        return PlanDeIntervencion::create($datos + [
            'centro_id' => $centro->id,
            'profesional_responsable_id' => $actor->id,
        ]);
    }

    /**
     * Rechaza un segundo plan vigente (borrador o activo) del mismo tipo en la historia (F3-1).
     *
     * El índice único parcial `planes_intervencion_un_vigente_por_tipo` lo garantiza en la
     * base de datos; esta comprobación da el mensaje legible.
     *
     * @param int $historiaId
     * @param TipoPlan $tipoPlan
     *
     * @return void
     *
     * @throws DomainException
     */
    public function exigirSinPlanVigenteDelTipo(int $historiaId, TipoPlan $tipoPlan): void
    {
        $existe = PlanDeIntervencion::withoutGlobalScopes()
            ->where('historia_id', $historiaId)
            ->where('tipo_plan_id', $tipoPlan->id)
            ->vigentes()
            ->exists();

        if ($existe) {
            throw new DomainException("La persona ya tiene un {$tipoPlan->nombre} en curso.");
        }
    }

    /**
     * Comprueba que el plan principal se abre en el centro de la historia y por quien puede.
     *
     * @param HistoriaSocial $historia
     * @param Centro $centro Centro en que se quiere abrir.
     * @param User $actor
     *
     * @return void
     *
     * @throws DomainException
     */
    private function exigirAperturaDelPrincipal(HistoriaSocial $historia, Centro $centro, User $actor): void
    {
        if ($historia->centro?->id !== $centro->id) {
            throw new DomainException('El plan principal solo se abre en el centro de la historia de la persona.');
        }

        $centrosUsuario = app(CentroDeUsuario::class);

        if ($centrosUsuario->supervisa($actor, $centro)) {
            return;
        }

        if (! $centrosUsuario->adscritoA($actor, $centro)) {
            throw new DomainException("Para abrir el plan principal tienes que estar adscrito a {$centro->nombre}.");
        }

        $referencia = app(AsignacionReferenciaService::class)->vigente($historia);

        if ($referencia !== null && $referencia->profesional_id !== $actor->id) {
            throw new DomainException('El plan principal lo abre el profesional de referencia o la supervisión del centro.');
        }
    }

    /**
     * Rechaza un tipo de plan que el centro no ofrece (docs/modulo-tipo-centro.md §6.2).
     *
     * Sin centro no se comprueba: la atención sin centro la decide RN-15.
     *
     * @param int $tipoPlanId Tipo de plan elegido.
     * @param Centro|null $centro Centro en que se abre el plan.
     *
     * @return void
     *
     * @throws DomainException
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
