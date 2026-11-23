<?php

namespace App\Filament\Resources\UsuarioResource\Pages;

use App\Filament\Concerns\AvisaDireccionDelCentro;
use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\UsuarioResource;
use Filament\Actions;

/**
 * Página de listado de usuarios.
 */
class ListUsuarios extends ListRecords
{
    // R3: botón «Avisar a la dirección del centro» de una desactivación bloqueada
    use AvisaDireccionDelCentro;

    protected static string $resource = UsuarioResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
