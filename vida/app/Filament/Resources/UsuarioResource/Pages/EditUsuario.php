<?php

namespace App\Filament\Resources\UsuarioResource\Pages;

use App\Filament\Acciones\DesactivarAction;
use App\Filament\Resources\UsuarioResource;
use App\Models\User;
use App\Models\UsuarioUo;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Modules\Intervencion\Services\Asignacion\SalidaCentroBloqueada;
use Modules\Intervencion\Services\Asignacion\SalidaCentroService;
use Modules\Usuarios\Services\AsignacionRolesService;
use Modules\Usuarios\Services\RolesSugeridosService;

/**
 * Página de edición de usuarios.
 *
 * Los cambios de roles pasan por AsignacionRolesService (historial y
 * supervisión). Si el cargo del profesional ha cambiado desde la última
 * revisión de roles, el formulario muestra un aviso que desaparece al editar
 * los roles o al descartarlo.
 *
 * Cerrar desde el formulario la adscripción a la UO de un centro exige RN-21: se
 * comprueba antes de guardar nada, y el modelo UsuarioUo lo vuelve a exigir.
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
     * Acciones de cabecera: descartar el aviso de cambio de cargo y desactivar (RN-20).
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
            DesactivarAction::usuario(),
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
     * Antes de guardar, rechaza el cierre de una adscripción a un centro con casos o
     * citas pendientes (RN-21), para no guardar el resto del formulario a medias.
     *
     * @return void
     */
    protected function beforeSave(): void
    {
        $estado = $this->form->getRawState()['adscripciones'] ?? [];

        foreach ($estado as $clave => $fila) {
            // Las filas existentes del repeater llegan como «record-{id}»
            if (! str_starts_with((string) $clave, 'record-')) {
                continue;
            }

            $adscripcion = UsuarioUo::find((int) substr((string) $clave, 7));

            if ($adscripcion === null || $adscripcion->usuario_id !== $this->usuario()->id) {
                continue;
            }

            $adscripcion->fecha_fin = $fila['fecha_fin'] ?? null;

            if (! $adscripcion->seCierra()) {
                continue;
            }

            try {
                app(SalidaCentroService::class)->exigirSalidaLibre($adscripcion);
            } catch (SalidaCentroBloqueada $e) {
                DesactivarAction::notificarBloqueo($e);
                $this->halt();
            }
        }
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
