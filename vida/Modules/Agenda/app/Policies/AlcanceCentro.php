<?php

namespace Modules\Agenda\Policies;

use App\Models\User;
use Modules\Centro\Models\Centro;

/**
 * Alcance por centro de las políticas de citas: un usuario actúa sobre las
 * citas y solicitudes de los centros a cuya UO está adscrito.
 */
final class AlcanceCentro
{
    /**
     * Si el usuario está adscrito (adscripción vigente) a la UO del centro.
     *
     * @param User $user
     * @param int $centroId
     * @return bool
     */
    public static function incluye(User $user, int $centroId): bool
    {
        $uoId = Centro::whereKey($centroId)->value('unidad_organizativa_id');

        return $uoId !== null && $user->uosActivas()->contains('id', $uoId);
    }
}
