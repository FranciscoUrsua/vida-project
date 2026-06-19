<?php

namespace Modules\Atencion\Policies;

use App\Models\User;
use Modules\Atencion\Models\RegistroAtencion;

/**
 * Policy de autorización para RegistroAtencion.
 *
 * Controla quién puede crear, ver o listar registros de atención.
 * Los registros ajenos (de otro profesional) requieren el permiso
 * atencion.leer_ajeno además de atencion.leer.
 */
class RegistroAtencionPolicy
{
    /**
     * Determina si el usuario puede crear un registro de atención.
     *
     * @param User $user Usuario autenticado.
     */
    public function create(User $user): bool
    {
        return $user->can('atencion.crear');
    }

    /**
     * Determina si el usuario puede ver un registro de atención concreto.
     *
     * El profesional autor del registro puede verlo siempre.
     * Ver registros ajenos requiere atencion.leer_ajeno.
     *
     * @param User $user Usuario autenticado.
     * @param RegistroAtencion $registro Registro de atención a consultar.
     */
    public function view(User $user, RegistroAtencion $registro): bool
    {
        if (! $user->can('atencion.leer')) {
            return false;
        }

        if ($registro->profesional_id === $user->id) {
            return true;
        }

        return $user->can('atencion.leer_ajeno');
    }

    /**
     * Determina si el usuario puede listar registros de atención.
     *
     * @param User $user Usuario autenticado.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('atencion.leer');
    }
}
