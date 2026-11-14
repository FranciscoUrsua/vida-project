<?php

namespace Modules\Intervencion\Policies;

use App\Models\HistoriaSocial;
use App\Models\User;
use App\Policies\CiudadanoPolicy;
use App\Services\AmbitoUsuario;
use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Services\Asignacion\AsignacionReferenciaService;
use Modules\Usuarios\Policies\HistoriaSocialPolicy;

/**
 * Policy de autorización para el Plan de Intervención.
 *
 * Implementa los tres pasos estándar de seguridad en profundidad:
 *
 *   Paso 1 — Permiso atómico: ¿tiene el usuario el permiso Spatie requerido?
 *   Paso 2 — Ámbito de UO: ¿el plan pertenece al ámbito de UO del usuario?
 *             La UO se obtiene vía Historia Social del plan.
 *             Nivel 1 = ámbito de UO → gestión completa.
 *             Nivel 2 = fuera del ámbito → solo lectura.
 *   Paso 3 — Colectivo protegido: solo en Nivel 2, verificar aprobación vigente
 *             (delegado a HistoriaSocialPolicy vía comprobación directa).
 *
 * Equipo del plan (fase 3, F3-3): el personal del centro de un plan en curso de la
 * persona ve sus planes y lee su historia aunque sea de otro centro. Editan un plan
 * solo su responsable, quien supervisa su centro y, en el plan principal (el PISO),
 * el profesional de referencia vigente. El TSR ve los demás planes y no los edita.
 *
 * Supervisión (decisión del 14 nov 2026): el rol `supervision` por sí solo no lee ni
 * edita planes (no tiene `plan.*`); la dirección que los necesite tiene además
 * `intervencion`, y entonces edita los planes de los centros que supervisa.
 *
 * Personas protegidas: la escritura exige además gestionarlas (RN-17 ampliada, F3-4).
 *
 * @see docs/modulo-intervencion.md
 * @see HistoriaSocialPolicy
 */
class PlanDeIntervencionPolicy
{
    use HandlesAuthorization;

    /** Permiso requerido para consultar un Plan de Intervención. */
    private const PERMISO_LEER = 'plan.leer';

    /** Permiso requerido para crear un Plan de Intervención. */
    private const PERMISO_CREAR = 'plan.crear';

    /** Permiso requerido para editar un Plan de Intervención. */
    private const PERMISO_EDITAR = 'plan.editar';

    /** Permiso requerido para eliminar (baja lógica) un Plan de Intervención. */
    private const PERMISO_ELIMINAR = 'plan.eliminar';

    /**
     * Decide si el usuario puede listar Planes de Intervención.
     *
     * @param User $usuario
     *
     * @return bool
     */
    public function viewAny(User $usuario): bool
    {
        return $usuario->can(self::PERMISO_LEER);
    }

    /**
     * Decide si el usuario puede consultar el Plan de Intervención.
     *
     * Permiso → gestión de la historia (ámbito y RN-17) o equipo de un plan en curso
     * de la persona (F3-3) → consulta libre sujeta a colectivo protegido.
     *
     * @param User $usuario
     * @param PlanDeIntervencion $plan
     *
     * @return bool
     */
    public function view(User $usuario, PlanDeIntervencion $plan): bool
    {
        if (! $usuario->can(self::PERMISO_LEER)) {
            return false;
        }

        $historia = $this->historiaDe($plan);

        if ($historia === null) {
            return false;
        }

        $ambito = app(AmbitoUsuario::class);

        if ($ambito->gestionaHistoria($usuario, $historia) || $ambito->leePorPlan($usuario, $historia)) {
            return true;
        }

        return $this->resolverConsultaExterna($usuario, $historia);
    }

    /**
     * Decide si el usuario puede crear un Plan de Intervención.
     *
     * Basta el permiso: dónde y qué tipo de plan lo decide PlanDeIntervencionService.
     * `supervision` sin `intervencion` no tiene `plan.crear`.
     *
     * @param User $usuario
     *
     * @return bool
     */
    public function create(User $usuario): bool
    {
        return $usuario->can(self::PERMISO_CREAR);
    }

    /**
     * Decide si el usuario puede editar el Plan de Intervención (F3-3).
     *
     * @param User $usuario
     * @param PlanDeIntervencion $plan
     *
     * @return bool
     */
    public function update(User $usuario, PlanDeIntervencion $plan): bool
    {
        return $usuario->can(self::PERMISO_EDITAR) && $this->escribe($usuario, $plan);
    }

    /**
     * Decide si el usuario puede eliminar (baja lógica) el Plan de Intervención; mismas reglas que editar.
     *
     * @param User $usuario
     * @param PlanDeIntervencion $plan
     *
     * @return bool
     */
    public function delete(User $usuario, PlanDeIntervencion $plan): bool
    {
        return $usuario->can(self::PERMISO_ELIMINAR) && $this->escribe($usuario, $plan);
    }

    // -------------------------------------------------------------------------
    // Métodos privados
    // -------------------------------------------------------------------------

    /**
     * Quién escribe en un plan (F3-3): su responsable, quien supervisa su centro y, en el
     * plan principal, el profesional de referencia vigente. Con persona protegida, además,
     * debe gestionarla (RN-17 ampliada, F3-4).
     *
     * @param User $usuario
     * @param PlanDeIntervencion $plan
     *
     * @return bool
     */
    private function escribe(User $usuario, PlanDeIntervencion $plan): bool
    {
        $historia = $this->historiaDe($plan);

        if ($historia === null) {
            return false;
        }

        $ambito = app(AmbitoUsuario::class);

        if ($ambito->historiaProtegida($historia) && ! $ambito->gestionaProtegido($usuario, $historia)) {
            return false;
        }

        if ($plan->profesional_responsable_id === $usuario->id) {
            return true;
        }

        // Planes antiguos sin centro: el de la historia.
        $centro = $plan->centro ?? $historia->centro;

        if ($centro !== null && app(CentroDeUsuario::class)->supervisa($usuario, $centro)) {
            return true;
        }

        return $plan->esPrincipal()
            && app(AsignacionReferenciaService::class)->vigente($historia)?->profesional_id === $usuario->id;
    }

    /**
     * Historia del plan, sin scope (la policy decide el acceso, no el scope).
     *
     * @param PlanDeIntervencion $plan
     *
     * @return HistoriaSocial|null
     */
    private function historiaDe(PlanDeIntervencion $plan): ?HistoriaSocial
    {
        return $plan->relationLoaded('historia') && $plan->historia !== null
            ? $plan->historia
            : HistoriaSocial::withoutGlobalScopes()->find($plan->historia_id);
    }

    /**
     * Resuelve el acceso en consulta libre (Nivel 2) delegando en CiudadanoPolicy.
     *
     * El criterio de colectivo protegido vive en un solo sitio (CLAUDE.md §3):
     * CiudadanoPolicy::consultaExternaPermitida().
     *
     * @param User $usuario
     * @param HistoriaSocial $historia
     *
     * @return bool
     */
    private function resolverConsultaExterna(User $usuario, HistoriaSocial $historia): bool
    {
        return app(CiudadanoPolicy::class)
            ->consultaExternaPermitida($usuario, $historia->ciudadano_id, (bool) $historia->ciudadano_protegido);
    }
}
