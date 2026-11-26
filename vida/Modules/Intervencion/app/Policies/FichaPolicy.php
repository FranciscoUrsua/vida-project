<?php

namespace Modules\Intervencion\Policies;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Services\ActuacionesEncargo;

/**
 * Quién ve una ficha de valoración.
 *
 * Dos vías:
 * - **historia**: quien puede leer la Historia Social de la ficha (`HistoriaSocialPolicy::view`);
 * - **encargo** (EA-7 de docs/instrucciones-cli/2026-11-encargo-actuaciones.md): el profesional
 *   de encargo, que no lee la Historia, solo ve las fichas que registró él desde un encargo suyo
 *   (`ActuacionesEncargo`). Nunca las de otros profesionales de la Historia.
 */
class FichaPolicy
{
    /**
     * Decide si el usuario puede ver la ficha.
     *
     * @param User $usuario
     * @param Ficha $ficha
     *
     * @return bool
     */
    public function view(User $usuario, Ficha $ficha): bool
    {
        // Vía encargo: lo suyo dentro de sus encargos
        if (app(ActuacionesEncargo::class)->esVisible($usuario, $ficha)) {
            return true;
        }

        // Vía historia: sin el scope de ámbito, para que decida la policy de la historia
        $historia = $ficha->historia_id !== null ? HistoriaSocial::withoutGlobalScopes()->find($ficha->historia_id) : null;

        return $historia !== null && Gate::forUser($usuario)->allows('view', $historia);
    }
}
