<?php

namespace Modules\Intervencion\Policies;

use App\Models\User;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Models\Apunte;

/**
 * Policy de autorización para Apunte del módulo Intervención.
 *
 * Implementa las tres reglas de visibilidad descritas en docs/modulo-intervencion.md §7.2:
 * - privada: solo el autor, sin excepción de rol ni jerarquía (precedencia absoluta)
 * - profesionales: accesible a cualquier profesional; el autor puede editar
 * - ciudadano: accesible a profesionales y, en el futuro, al ciudadano vía carpeta ciudadana
 *
 * Regla adicional de eliminación: solo los apuntes privados pueden eliminarse
 * (son notas personales del profesional). Los apuntes de visibilidad profesionales
 * o ciudadano son registro permanente e ineliminable por diseño.
 */
class ApuntePolicy
{
    /**
     * Determina si el usuario puede ver el apunte.
     *
     * Regla absoluta: si visibilidad=privada, solo el autor tiene acceso.
     *
     * @param User $usuario
     * @param Apunte $apunte
     *
     * @return bool
     */
    public function view(User $usuario, Apunte $apunte): bool
    {
        if ($apunte->visibilidad === VisibilidadApunte::Privada) {
            return $usuario->id === $apunte->autor_id;
        }

        return true;
    }

    /**
     * Determina si el usuario puede editar el apunte.
     *
     * Solo el autor puede editar sus propios apuntes.
     *
     * @param User $usuario
     * @param Apunte $apunte
     *
     * @return bool
     */
    public function update(User $usuario, Apunte $apunte): bool
    {
        return $usuario->id === $apunte->autor_id;
    }

    /**
     * Determina si el usuario puede eliminar el apunte.
     *
     * Solo se pueden eliminar apuntes privados (notas personales del profesional).
     * Los apuntes de visibilidad profesionales o ciudadano son registro permanente.
     *
     * @param User $usuario
     * @param Apunte $apunte
     *
     * @return bool
     */
    public function delete(User $usuario, Apunte $apunte): bool
    {
        return $apunte->visibilidad === VisibilidadApunte::Privada
            && $usuario->id === $apunte->autor_id;
    }
}
