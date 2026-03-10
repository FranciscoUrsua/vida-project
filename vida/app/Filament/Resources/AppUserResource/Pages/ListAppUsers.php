<?php

namespace App\Filament\Resources\AppUserResource\Pages;

use App\Filament\Resources\AppUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAppUsers extends ListRecords
{
    protected static string $resource = AppUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
