<?php

namespace App\Filament\Resources\AppUserResource\Pages;

use App\Filament\Resources\AppUserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAppUser extends EditRecord
{
    protected static string $resource = AppUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
