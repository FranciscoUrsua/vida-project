<?php

namespace Modules\Documentos\Policies;

use App\Models\HistoriaSocial;
use App\Models\User;
use App\Services\AmbitoUsuario;
use Illuminate\Support\Facades\Gate;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Models\PlanDeIntervencion;

/**
 * Autorización del PISO firmado de un plan (fase 5, paso 4, F5-4).
 *
 * - **subir**: solo en el plan principal (PISO, `general_asp`) activo, y solo su
 *   responsable o la supervisión de su centro. Una persona protegida exige además
 *   gestionarla (RN-17 ampliada), como cualquier escritura del plan.
 * - **ver**: quien puede ver el plan.
 */
class PisoFirmadoPolicy
{
    /**
     * Decide si el usuario puede subir (o sustituir) el PISO firmado del plan.
     *
     * @param User $usuario Usuario autenticado.
     * @param PlanDeIntervencion $plan Plan de intervención.
     *
     * @return bool
     */
    public function subir(User $usuario, PlanDeIntervencion $plan): bool
    {
        if (! $plan->esPrincipal() || $plan->estado !== EstadoPlan::Activo) {
            return false;
        }

        $historia = HistoriaSocial::withoutGlobalScopes()->find($plan->historia_id);
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

        $centro = $plan->centro ?? $historia->centro;

        return $centro !== null && app(CentroDeUsuario::class)->supervisa($usuario, $centro);
    }

    /**
     * Decide si el usuario puede ver el PISO firmado del plan.
     *
     * @param User $usuario Usuario autenticado.
     * @param PlanDeIntervencion $plan Plan de intervención.
     *
     * @return bool
     */
    public function ver(User $usuario, PlanDeIntervencion $plan): bool
    {
        return Gate::forUser($usuario)->allows('view', $plan);
    }
}
