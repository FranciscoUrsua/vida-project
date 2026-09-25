<?php

namespace App\Filament\Resources\UsuarioResource\Pages;

use App\Filament\Resources\UsuarioResource;
use App\Models\User;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Modules\Usuarios\Services\AsignacionRolesService;
use Modules\Usuarios\Services\RolesSugeridosService;

/**
 * Página de edición de usuarios.
 *
 * Los cambios de roles pasan por AsignacionRolesService (historial y
 * supervisión). Si el cargo del profesional ha cambiado desde la última
 * revisión de roles, el formulario muestra un aviso que desaparece al editar
 * los roles o al descartarlo.
 */
class EditUsuario extends EditRecord
{
    protected static string $resource = UsuarioResource::class;

    /**
     * Roles marcados en el formulario, pendientes de sincronizar tras guardar.
     *
     * @var list<int>
     */
    protected array $rolesMarcados = [];

    /**
     * Acciones de cabecera: descartar el aviso de cambio de cargo y borrar.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('descartarAvisoCargo')
                ->label('Descartar aviso de cargo')
                ->icon('heroicon-o-check')
                ->color('gray')
                ->visible(fn (): bool => app(RolesSugeridosService::class)->avisoCambioCargo($this->usuario()) !== null)
                ->action(fn () => app(RolesSugeridosService::class)->marcarRevisado($this->usuario())),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Carga en el selector los roles efectivos y los pendientes de aprobación.
     *
     * @param array<string, mixed> $data Datos del registro.
     *
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['roles'] = app(AsignacionRolesService::class)->rolesSeleccionados($this->usuario());

        return $data;
    }

    /**
     * Separa los roles del resto de datos: no son un atributo de User.
     *
     * @param array<string, mixed> $data Datos del formulario.
     *
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->rolesMarcados = array_map('intval', $data['roles'] ?? []);
        unset($data['roles']);

        return $data;
    }

    /**
     * Sincroniza los roles y, si han cambiado, da por revisado el cargo actual.
     */
    protected function afterSave(): void
    {
        /** @var User|null $autor */
        $autor = auth()->user();

        $cambiado = app(AsignacionRolesService::class)->sincronizar($this->usuario(), $this->rolesMarcados, $autor);

        if ($cambiado) {
            app(RolesSugeridosService::class)->marcarRevisado($this->usuario());
        }
    }

    /**
     * Usuario en edición con su tipo concreto.
     */
    private function usuario(): User
    {
        /** @var User $usuario */
        $usuario = $this->getRecord();

        return $usuario;
    }
}
