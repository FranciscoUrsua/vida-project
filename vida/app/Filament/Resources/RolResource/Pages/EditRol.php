<?php

namespace App\Filament\Resources\RolResource\Pages;

use App\Filament\Resources\RolResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Usuarios\Models\ConfiguracionRol;
use Spatie\Permission\Models\Role;

/**
 * Página de edición de un rol: permisos y nivel de supervisión de su asignación.
 */
class EditRol extends EditRecord
{
    protected static string $resource = RolResource::class;

    /**
     * Añade al formulario el nivel de supervisión, que no es columna del rol.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Role $rol */
        $rol = $this->getRecord();
        $data['nivel_supervision'] = ConfiguracionRol::nivelPara($rol);

        return $data;
    }

    /**
     * Guarda el rol y, aparte, su nivel de supervisión en configuracion_roles.
     *
     * @param Model $record
     * @param array<string, mixed> $data
     * @return Model
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $nivel = $data['nivel_supervision'];
        unset($data['nivel_supervision']);

        $record = parent::handleRecordUpdate($record, $data);

        /** @var Role $record */
        ConfiguracionRol::fijarNivel($record, $nivel);

        return $record;
    }
}
