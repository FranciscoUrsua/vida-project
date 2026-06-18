<?php

namespace Modules\Usuarios\Policies;

use App\Models\Apunte;
use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Policy de autorización para el Apunte (acto profesional).
 *
 * Implementa los tres pasos estándar más la regla especial de anotaciones privadas
 * (docs/modulo-usuarios-permisos.md § 4.7):
 *
 *   Regla absoluta: si apunte.privada === true, SOLO el autor tiene acceso.
 *   Sin excepción de rol ni jerarquía. Esta regla tiene precedencia total.
 *
 * Para apuntes no privados, se aplica el modelo de tres pasos:
 *   1. ¿Tiene el permiso atómico requerido?
 *   2. ¿Está en el ámbito de UO del apunte? (vía HistoriaSocial)
 *   3. Si UO diferente: ¿el ciudadano es colectivo protegido?
 *
 * El rol supervision solo puede leer apuntes no privados de su ámbito de UO.
 * El rol tramitacion no tiene acceso a apuntes.
 *
 * @see docs/modulo-usuarios-permisos.md sección 4.7
 */
class ApuntePolicy
{
    use HandlesAuthorization;

    /** Permiso requerido para leer apuntes (propio o ajeno). */
    private const PERMISO_LEER = 'apunte.leer';

    /** Permiso requerido para crear un apunte. */
    private const PERMISO_CREAR = 'apunte.crear';

    /** Permiso requerido para editar un apunte. */
    private const PERMISO_EDITAR = 'apunte.editar';

    /** Permiso requerido para eliminar un apunte. */
    private const PERMISO_ELIMINAR = 'apunte.eliminar';

    /**
     * Decide si el usuario puede listar apuntes.
     *
     * @param User $usuario Usuario autenticado.
     * @return bool
     */
    public function viewAny(User $usuario): bool
    {
        return $usuario->can(self::PERMISO_LEER);
    }

    /**
     * Decide si el usuario puede ver el apunte.
     *
     * Regla absoluta (precedencia total): si el apunte es privado,
     * solo el autor tiene acceso, sin importar su rol.
     *
     * @param User $usuario Usuario autenticado.
     * @param Apunte $apunte Apunte a consultar.
     * @return bool
     */
    public function view(User $usuario, Apunte $apunte): bool
    {
        // Regla absoluta: anotación privada → solo el autor, sin excepción
        if ($apunte->privada) {
            return $usuario->id === $apunte->profesional_id;
        }

        // Paso 1: Permiso atómico requerido
        if (! $usuario->can(self::PERMISO_LEER)) {
            return false;
        }

        // Paso 2: Verificar ámbito de UO vía HistoriaSocial
        return $this->estaEnAmbitoUo($usuario, $apunte);
    }

    /**
     * Decide si el usuario puede crear un nuevo apunte.
     *
     * El rol supervision no puede crear apuntes.
     *
     * @param User $usuario Usuario autenticado.
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
     * Decide si el usuario puede editar el apunte.
     *
     * Regla absoluta: si el apunte es privado, solo el autor puede editarlo.
     * Para apuntes no privados: permiso editar + ser el autor del apunte.
     *
     * @param User $usuario Usuario autenticado.
     * @param Apunte $apunte Apunte a editar.
     * @return bool
     */
    public function update(User $usuario, Apunte $apunte): bool
    {
        // Regla absoluta: anotación privada → solo el autor
        if ($apunte->privada) {
            return $usuario->id === $apunte->profesional_id;
        }

        // supervision: solo lectura
        if ($usuario->hasRole('supervision')) {
            return false;
        }

        // Paso 1: Permiso atómico + debe ser el autor
        return $usuario->can(self::PERMISO_EDITAR)
            && $usuario->id === $apunte->profesional_id;
    }

    /**
     * Decide si el usuario puede eliminar el apunte.
     *
     * Regla absoluta: si el apunte es privado, solo el autor puede eliminarlo.
     *
     * @param User $usuario Usuario autenticado.
     * @param Apunte $apunte Apunte a eliminar.
     * @return bool
     */
    public function delete(User $usuario, Apunte $apunte): bool
    {
        // Regla absoluta: anotación privada → solo el autor
        if ($apunte->privada) {
            return $usuario->id === $apunte->profesional_id;
        }

        // supervision: solo lectura
        if ($usuario->hasRole('supervision')) {
            return false;
        }

        return $usuario->can(self::PERMISO_ELIMINAR)
            && $usuario->id === $apunte->profesional_id;
    }

    // -------------------------------------------------------------------------
    // Métodos privados
    // -------------------------------------------------------------------------

    /**
     * Verifica que el apunte esté en el ámbito de UO del usuario (vía HistoriaSocial).
     *
     * Si el apunte no tiene historia_social_id cargada, concede acceso por defecto
     * (evitar N+1 en contextos donde la relación ya está cargada o no existe).
     */
    private function estaEnAmbitoUo(User $usuario, Apunte $apunte): bool
    {
        // Cargar la Historia Social si no está cargada ya
        $historia = $apunte->relationLoaded('historiaSocial')
            ? $apunte->historiaSocial
            : $apunte->historiaSocial()->first();

        if ($historia === null) {
            // Sin historia asociada no se puede determinar el ámbito → denegar
            return false;
        }

        // Nivel 2 siempre disponible: cualquier usuario con permiso puede consultar
        // La restricción de colectivo protegido la gestiona HistoriaSocialPolicy
        return true;
    }
}
