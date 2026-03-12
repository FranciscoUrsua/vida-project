<?php

namespace Modules\Usuarios\Policies;

use App\Models\Apunte;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Policy de autorización para el Apunte (acto profesional).
 *
 * Además de los cuatro pasos estándar, aplica la regla especial de
 * anotaciones privadas (docs/modulo-usuarios-permisos.md § 4.7):
 *
 *   Si apunte.privada === true, SOLO el autor tiene acceso, sin excepción
 *   de rol ni jerarquía. Esta regla tiene precedencia sobre cualquier otra.
 *
 * @see docs/modulo-usuarios-permisos.md sección 4.7
 */
class ApuntePolicy
{
    use HandlesAuthorization;

    /**
     * Decide si el usuario puede ver el apunte.
     *
     * Regla especial (precedencia absoluta): si el apunte es privado,
     * solo el autor tiene acceso, sin importar su rol.
     *
     * @param User $usuario
     * @param Apunte $apunte
     * @return bool
     */
    public function view(User $usuario, Apunte $apunte): bool
    {
        // Regla absoluta: anotación privada → solo el autor, sin excepción
        if ($apunte->privada) {
            return $usuario->id === $apunte->profesional_id;
        }

        $esAutor = $usuario->id === $apunte->profesional_id;

        if ($esAutor) {
            return $usuario->can('apunte.leer_propio');
        }

        return $usuario->can('apunte.leer_ajeno');
    }

    /**
     * Decide si el usuario puede crear un nuevo apunte.
     *
     * @param User $usuario
     * @return bool
     */
    public function create(User $usuario): bool
    {
        return $usuario->can('apunte.crear');
    }

    /**
     * Decide si el usuario puede editar el apunte.
     *
     * @param User $usuario
     * @param Apunte $apunte
     * @return bool
     */
    public function update(User $usuario, Apunte $apunte): bool
    {
        if ($apunte->privada) {
            return $usuario->id === $apunte->profesional_id;
        }

        return $usuario->id === $apunte->profesional_id
            && $usuario->can('apunte.leer_propio');
    }

    /**
     * Decide si el usuario puede eliminar el apunte.
     *
     * @param User $usuario
     * @param Apunte $apunte
     * @return bool
     */
    public function delete(User $usuario, Apunte $apunte): bool
    {
        if ($apunte->privada) {
            return $usuario->id === $apunte->profesional_id;
        }

        return $usuario->id === $apunte->profesional_id;
    }
}
