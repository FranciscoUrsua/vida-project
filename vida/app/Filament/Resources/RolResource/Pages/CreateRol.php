<?php

namespace App\Filament\Resources\RolResource\Pages;

use App\Filament\Resources\RolResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Usuarios\Models\ConfiguracionRol;
use Spatie\Permission\Models\Role;

/**
 * Página de creación de un rol: permisos y nivel de supervisión de su asignación.
 */
class CreateRol extends CreateRecord
{
    protected static string $resource = RolResource::class;

    /**
     * Crea el rol y, aparte, su nivel de supervisión en configuracion_roles.
     *
     * @param array<string, mixed> $data
     * @return Model
     */
    protected function handleRecordCreation(array $data): Model
    {
        $nivel = $data['nivel_supervision'];
        unset($data['nivel_supervision']);

        $record = parent::handleRecordCreation($data);

        /** @var Role $record */
        ConfiguracionRol::fijarNivel($record, $nivel);

        return $record;
    }
}
