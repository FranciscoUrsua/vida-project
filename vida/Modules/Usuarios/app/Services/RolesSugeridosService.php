<?php

namespace Modules\Usuarios\Services;

use App\Models\User;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;

/**
 * Lectura de los roles sugeridos por cargo para el alta de usuario y el aviso de cambio de cargo.
 *
 * Único punto de lectura de cargo_roles_sugeridos (sección 2.9). Nunca asigna
 * ni retira roles: los roles se asignan individualmente por AsignacionRolesService
 * a partir de lo que adm_usuarios decide en el formulario (principio 3.3).
 *
 * @see docs/modulo-usuarios-permisos.md sección 2.9
 */
class RolesSugeridosService
{
    /**
     * Nombres de los roles sugeridos para el cargo del profesional indicado.
     *
     * @param int|null $profesionalId Profesional elegido en el formulario.
     *
     * @return list<string> Vacío si no hay profesional o su cargo no tiene sugerencias.
     */
    public function paraProfesional(?int $profesionalId): array
    {
        if ($profesionalId === null) {
            return [];
        }

        $cargoId = Profesional::whereKey($profesionalId)->value('cargo_id');

        return $cargoId === null ? [] : $this->paraCargo((int) $cargoId);
    }

    /**
     * Aviso de cambio de cargo pendiente de revisar para un usuario, si lo hay.
     *
     * Existe cuando el cargo actual de su profesional difiere del cargo para el
     * que se revisaron por última vez sus roles.
     *
     * @param User $usuario Usuario consultado.
     *
     * @return array{cargo: string, roles: list<string>}|null
     */
    public function avisoCambioCargo(User $usuario): ?array
    {
        $cargoActual = $usuario->profesional?->cargo_id;

        if ($cargoActual === null || (int) $cargoActual === (int) $usuario->cargo_roles_revisado_id) {
            return null;
        }

        return [
            'cargo' => (string) Cargo::whereKey($cargoActual)->value('nombre'),
            'roles' => $this->paraCargo((int) $cargoActual),
        ];
    }

    /**
     * Marca los roles del usuario como revisados para el cargo actual de su profesional.
     *
     * Se llama al dar de alta, al editar los roles o al descartar el aviso.
     *
     * @param User $usuario Usuario revisado.
     */
    public function marcarRevisado(User $usuario): void
    {
        $usuario->forceFill(['cargo_roles_revisado_id' => $usuario->profesional?->cargo_id])->save();
    }

    /**
     * Nombres de los roles sugeridos de un cargo, en orden alfabético.
     *
     * @param int $cargoId Cargo consultado.
     *
     * @return list<string>
     */
    private function paraCargo(int $cargoId): array
    {
        return Cargo::find($cargoId)?->rolesSugeridos()->orderBy('rol')->pluck('rol')->all() ?? [];
    }
}
