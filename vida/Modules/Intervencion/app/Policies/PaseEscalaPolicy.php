<?php

namespace Modules\Intervencion\Policies;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Escalas\Models\PaseEscala;
use Modules\Intervencion\Services\ActuacionesEncargo;

/**
 * Quién ve un pase de escala.
 *
 * Dos vías:
 * - **historia**: quien puede leer la Historia Social del pase (`HistoriaSocialPolicy::view`);
 * - **encargo** (EA-7 de docs/instrucciones-cli/2026-11-encargo-actuaciones.md): el profesional
 *   de encargo solo ve los pases que registró él desde un encargo suyo (`ActuacionesEncargo`).
 *
 * Vive en Intervención y no en Escalas porque la vía de encargo es de este módulo.
 */
class PaseEscalaPolicy
{
    /**
     * Decide si el usuario puede ver el pase.
     *
     * @param User $usuario
     * @param PaseEscala $pase
     *
     * @return bool
     */
    public function view(User $usuario, PaseEscala $pase): bool
    {
        // Vía encargo: lo suyo dentro de sus encargos
        if (app(ActuacionesEncargo::class)->esVisible($usuario, $pase)) {
            return true;
        }

        // Vía historia: sin el scope de ámbito, para que decida la policy de la historia
        $historia = HistoriaSocial::withoutGlobalScopes()->find($pase->historia_id);

        return $historia !== null && Gate::forUser($usuario)->allows('view', $historia);
    }
}
