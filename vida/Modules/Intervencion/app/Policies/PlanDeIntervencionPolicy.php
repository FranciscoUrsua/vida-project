<?php

namespace Modules\Intervencion\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Intervencion\Models\PlanDeIntervencion;

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
 * Caso especial — supervision: solo lectura, nunca escritura.
 * Caso especial — adm_sistema: también debe pasar los filtros de UO y colectivo protegido.
 *
 * @see docs/modulo-intervencion.md
 * @see \Modules\Usuarios\Policies\HistoriaSocialPolicy
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
     * @return bool
     */
    public function viewAny(User $usuario): bool
    {
        return $usuario->can(self::PERMISO_LEER);
    }

    /**
     * Decide si el usuario puede consultar el Plan de Intervención.
     *
     * Evaluación en tres pasos: permiso → ámbito UO → colectivo protegido.
     *
     * @param User $usuario
     * @param PlanDeIntervencion $plan
     * @return bool
     */
    public function view(User $usuario, PlanDeIntervencion $plan): bool
    {
        // Paso 1: Permiso atómico requerido
        if (! $usuario->can(self::PERMISO_LEER)) {
            return false;
        }

        // Paso 2: Verificar ámbito de UO vía Historia Social
        $historia = $plan->relationLoaded('historia')
            ? $plan->historia
            : $plan->historia()->first();

        if ($historia === null) {
            return false;
        }

        $uoIds = $usuario->uoSubtreeIds();

        if (in_array($historia->unidad_organizativa_id, $uoIds)) {
            // Nivel 1: gestión completa dentro del ámbito de UO
            return true;
        }

        // Paso 3: Nivel 2 — consulta libre sujeta a colectivo protegido
        return $this->resolverConsultaExterna($usuario, $historia);
    }

    /**
     * Decide si el usuario puede crear un Plan de Intervención.
     *
     * El rol supervision no puede crear planes.
     *
     * @param User $usuario
     * @return bool
     */
    public function create(User $usuario): bool
    {
        // supervision: solo lectura
        if ($usuario->hasRole('supervision')) {
            return false;
        }

        return $usuario->can(self::PERMISO_CREAR);
    }

    /**
     * Decide si el usuario puede editar el Plan de Intervención.
     *
     * La edición solo está permitida dentro del ámbito de UO del usuario.
     * El rol supervision no puede editar.
     *
     * @param User $usuario
     * @param PlanDeIntervencion $plan
     * @return bool
     */
    public function update(User $usuario, PlanDeIntervencion $plan): bool
    {
        // supervision: solo lectura
        if ($usuario->hasRole('supervision')) {
            return false;
        }

        // Paso 1: Permiso atómico requerido
        if (! $usuario->can(self::PERMISO_EDITAR)) {
            return false;
        }

        // Paso 2: Solo permite editar si el plan está en el ámbito de UO
        $historia = $plan->relationLoaded('historia')
            ? $plan->historia
            : $plan->historia()->first();

        if ($historia === null) {
            return false;
        }

        $uoIds = $usuario->uoSubtreeIds();

        return in_array($historia->unidad_organizativa_id, $uoIds);
    }

    /**
     * Decide si el usuario puede eliminar (baja lógica) el Plan de Intervención.
     *
     * La eliminación solo está permitida dentro del ámbito de UO del usuario.
     *
     * @param User $usuario
     * @param PlanDeIntervencion $plan
     * @return bool
     */
    public function delete(User $usuario, PlanDeIntervencion $plan): bool
    {
        // supervision: solo lectura
        if ($usuario->hasRole('supervision')) {
            return false;
        }

        // Paso 1: Permiso atómico requerido
        if (! $usuario->can(self::PERMISO_ELIMINAR)) {
            return false;
        }

        // Paso 2: Solo permite eliminar si el plan está en el ámbito de UO
        $historia = $plan->relationLoaded('historia')
            ? $plan->historia
            : $plan->historia()->first();

        if ($historia === null) {
            return false;
        }

        $uoIds = $usuario->uoSubtreeIds();

        return in_array($historia->unidad_organizativa_id, $uoIds);
    }

    // -------------------------------------------------------------------------
    // Métodos privados
    // -------------------------------------------------------------------------

    /**
     * Resuelve el acceso en consulta libre (Nivel 2) verificando colectivo protegido.
     *
     * Si el ciudadano asociado a la Historia es especialmente protegido,
     * se requiere aprobación vigente (Nivel 3).
     *
     * @param User $usuario
     * @param \App\Models\HistoriaSocial $historia
     * @return bool
     */
    private function resolverConsultaExterna(User $usuario, \App\Models\HistoriaSocial $historia): bool
    {
        if (! $historia->ciudadano_protegido) {
            return true; // Nivel 2: consulta libre
        }

        // Nivel 3: ciudadano protegido → requiere aprobación vigente
        return \App\Models\AccesoProtegido::where('usuario_id', $usuario->id)
            ->where('ciudadano_id', $historia->ciudadano_id)
            ->where('estado', 'aprobado')
            ->where(function ($consulta) {
                $consulta->whereNull('acceso_valido_hasta')
                         ->orWhere('acceso_valido_hasta', '>=', now());
            })
            ->exists();
    }
}
