<?php

namespace App\Policies;

use App\Models\Apunte;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Policy de autorización para el Apunte (acto profesional).
 *
 * Además de los cuatro pasos estándar de autorización, aplica la regla
 * especial de anotaciones privadas (docs/modulo-usuarios-permisos.md § 4.7):
 *
 *   Si apunte.privada === true, SOLO el autor tiene acceso, sin excepción
 *   de rol ni jerarquía. Esta regla tiene precedencia sobre cualquier otra.
 *
 * Un apunte puede ser de tipo: valoración, seguimiento, anotación,
 * entrevista, gestión/coordinación, informe social, derivación
 * (docs/glosario.md § Apunte).
 *
 * @see docs/modulo-usuarios-permisos.md sección 4.7
 * @see docs/glosario.md § Apunte
 */
class ApuntePolicy
{
    use HandlesAuthorization;

    // -------------------------------------------------------------------------
    // Métodos de autorización
    // -------------------------------------------------------------------------

    /**
     * Decide si el usuario puede ver el apunte.
     *
     * Regla especial (precedencia absoluta): si el apunte es privado,
     * solo el autor tiene acceso, sin importar su rol.
     *
     * Para apuntes no privados: el usuario necesita el permiso
     * apunte.leer_propio (si es el autor) o apunte.leer_ajeno (si no lo es).
     *
     * @param User $usuario Usuario autenticado
     * @param Apunte $apunte Apunte al que se solicita acceso
     * @return bool
     */
    public function view(User $usuario, Apunte $apunte): bool
    {
        // Regla absoluta: anotación privada → solo el autor, sin excepción
        if ($apunte->privada) {
            return $usuario->id === $apunte->profesional_id;
        }

        $esAutor = $usuario->id === $apunte->profesional_id;

        // Apunte propio no privado
        if ($esAutor) {
            return $usuario->can('apunte.leer_propio');
        }

        // Apunte de otro profesional
        return $usuario->can('apunte.leer_ajeno');
    }

    /**
     * Decide si el usuario puede crear un nuevo apunte.
     *
     * Requiere el permiso apunte.crear. Cualquier usuario con ese permiso
     * puede crear apuntes (incluidos privados, que son para uso personal).
     *
     * @param User $usuario Usuario autenticado
     * @return bool
     */
    public function create(User $usuario): bool
    {
        return $usuario->can('apunte.crear');
    }

    /**
     * Decide si el usuario puede editar el apunte.
     *
     * Regla especial (precedencia absoluta): si el apunte es privado,
     * solo el autor puede modificarlo.
     *
     * Para apuntes no privados: solo el propio autor puede editarlos,
     * y solo si tiene el permiso apunte.leer_propio (no hay permiso
     * específico de edición de apuntes; el acto profesional es inmutable
     * una vez cerrado en futuras implementaciones).
     *
     * @param User $usuario Usuario autenticado
     * @param Apunte $apunte Apunte a editar
     * @return bool
     *
     * @todo Añadir lógica de inmutabilidad cuando se implemente el ciclo
     *       de vida del apunte (apunte firmado = solo lectura).
     */
    public function update(User $usuario, Apunte $apunte): bool
    {
        // Regla absoluta: anotación privada → solo el autor
        if ($apunte->privada) {
            return $usuario->id === $apunte->profesional_id;
        }

        // Apunte no privado: solo el autor puede editarlo
        return $usuario->id === $apunte->profesional_id
            && $usuario->can('apunte.leer_propio');
    }

    /**
     * Decide si el usuario puede eliminar el apunte.
     *
     * Regla especial (precedencia absoluta): si el apunte es privado,
     * solo el autor puede eliminarlo.
     *
     * @param User $usuario Usuario autenticado
     * @param Apunte $apunte Apunte a eliminar
     * @return bool
     */
    public function delete(User $usuario, Apunte $apunte): bool
    {
        // Regla absoluta: anotación privada → solo el autor
        if ($apunte->privada) {
            return $usuario->id === $apunte->profesional_id;
        }

        // Solo el autor puede eliminar sus propios apuntes
        return $usuario->id === $apunte->profesional_id;
    }
}
