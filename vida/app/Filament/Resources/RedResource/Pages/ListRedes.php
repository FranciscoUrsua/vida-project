<?php

namespace App\Filament\Resources\RedResource\Pages;

use App\Filament\Resources\RedResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRedes extends ListRecords
{
    protected static string $resource = RedResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
