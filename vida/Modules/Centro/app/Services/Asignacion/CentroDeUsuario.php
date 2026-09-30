<?php

namespace Modules\Centro\Services\Asignacion;

use App\Models\User;
use Modules\Centro\Models\Centro;

/**
 * Resuelve en qué centro actúa un usuario y si lo supervisa.
 *
 * El centro de un usuario es el de su UO activa (Centro.unidad_organizativa_id),
 * el mismo criterio que ya usa EquipoPage::centroActivo(). Es el centro de la
 * apertura de una historia y la bandeja en la que aparece lo que el usuario
 * provoca y el sistema no puede decidir solo.
 */
class CentroDeUsuario
{
    /**
     * Centro de la UO activa del usuario, o null si no tiene o su UO no tiene centro.
     *
     * @param User|null $usuario
     * @return Centro|null
     */
    public function centroActivo(?User $usuario): ?Centro
    {
        $uoId = $usuario?->uosActivas()->first()?->id;

        return $uoId ? Centro::where('unidad_organizativa_id', $uoId)->first() : null;
    }

    /**
     * Si el usuario tiene rol de supervisión y está adscrito a la UO del centro.
     *
     * @param User $usuario
     * @param Centro $centro
     * @return bool
     */
    public function supervisa(User $usuario, Centro $centro): bool
    {
        return $centro->unidad_organizativa_id !== null
            && $usuario->hasRole('supervision')
            && $usuario->uosActivas()->contains('id', $centro->unidad_organizativa_id);
    }
}
