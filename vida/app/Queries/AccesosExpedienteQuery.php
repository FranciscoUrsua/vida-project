<?php

namespace App\Queries;

use App\Models\Audit;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Intervencion\Models\PlanDeIntervencion;

/**
 * Query object para obtener los accesos al expediente de un ciudadano
 * filtrados por la visibilidad del usuario autenticado.
 *
 * Visibilidad (spec §5 docs/modulo-auditoria.md):
 *   - adm_sistema → todos los accesos sin restricción.
 *   - TSR responsable del plan activo de la historia → todos los accesos.
 *   - Supervisor con la UO de la historia en su árbol → todos los accesos.
 *   - Cualquier otro profesional → únicamente sus propios accesos.
 *
 * @see docs/modulo-auditoria.md §5
 * @see docs/instrucciones-cli/ui-intervencion-accesos-auditoria.md §2
 */
class AccesosExpedienteQuery
{
    /**
     * Construye el Builder de auditoría filtrado según el rol del usuario.
     *
     * @param User $user          Usuario autenticado
     * @param Ciudadano $ciudadano Ciudadano cuyo expediente se consulta
     * @param HistoriaSocial $historia Historia social asociada
     * @return Builder<Audit>
     */
    public function paraUsuario(User $user, Ciudadano $ciudadano, HistoriaSocial $historia): Builder
    {
        $base = Audit::where('ciudadano_id', $ciudadano->id)
            ->with('user.profesional')
            ->orderBy('created_at', 'desc');

        if ($user->hasRole('adm_sistema')) {
            return $base;
        }

        // TSR: profesional responsable de cualquier plan de la historia
        $esTSR = PlanDeIntervencion::withoutGlobalScopes()
            ->where('historia_id', $historia->id)
            ->where('profesional_responsable_id', $user->id)
            ->exists();

        if ($esTSR) {
            return $base;
        }

        // Supervisor cuya UO incluye la UO de la historia
        if ($user->hasRole('supervision')
            && in_array($historia->unidad_organizativa_id, $user->uoSubtreeIds())) {
            return $base;
        }

        // Resto: solo sus propios accesos
        return $base->where('user_id', $user->id);
    }

    /**
     * Indica si el usuario puede ver todos los accesos o únicamente los propios.
     *
     * @param User $user
     * @param HistoriaSocial $historia
     *
     * @return bool True si el usuario puede ver todos los accesos del expediente.
     */
    public function puedeVerTodos(User $user, HistoriaSocial $historia): bool
    {
        if ($user->hasRole('adm_sistema')) {
            return true;
        }

        $esTSR = PlanDeIntervencion::withoutGlobalScopes()
            ->where('historia_id', $historia->id)
            ->where('profesional_responsable_id', $user->id)
            ->exists();

        if ($esTSR) {
            return true;
        }

        return $user->hasRole('supervision')
            && in_array($historia->unidad_organizativa_id, $user->uoSubtreeIds());
    }
}
