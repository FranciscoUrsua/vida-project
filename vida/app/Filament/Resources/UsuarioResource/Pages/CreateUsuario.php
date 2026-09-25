<?php

namespace App\Filament\Resources\UsuarioResource\Pages;

use App\Filament\Resources\UsuarioResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Modules\Usuarios\Services\AsignacionRolesService;
use Modules\Usuarios\Services\RolesSugeridosService;

/**
 * Página de creación de usuarios.
 *
 * Los roles marcados (pre-rellenados con las sugerencias del cargo o elegidos
 * a mano) se asignan tras crear el usuario y sus adscripciones, por el flujo
 * supervisado de AsignacionRolesService.
 */
class CreateUsuario extends CreateRecord
{
    protected static string $resource = UsuarioResource::class;

    /**
     * Roles marcados en el formulario, pendientes de asignar tras la creación.
     *
     * @var list<int>
     */
    protected array $rolesMarcados = [];

    /**
     * Separa los roles del resto de datos: no son un atributo de User.
     *
     * @param array<string, mixed> $data Datos del formulario.
     *
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->rolesMarcados = array_map('intval', $data['roles'] ?? []);
        unset($data['roles']);

        return $data;
    }

    /**
     * Asigna los roles marcados y deja los roles revisados para el cargo actual.
     *
     * Se ejecuta después de guardar las adscripciones, para que la alerta
     * supervisada llegue a la UO del usuario.
     */
    protected function afterCreate(): void
    {
        /** @var User $usuario */
        $usuario = $this->getRecord();
        /** @var User|null $autor */
        $autor = auth()->user();

        app(AsignacionRolesService::class)->sincronizar($usuario, $this->rolesMarcados, $autor);
        app(RolesSugeridosService::class)->marcarRevisado($usuario);
    }
}
