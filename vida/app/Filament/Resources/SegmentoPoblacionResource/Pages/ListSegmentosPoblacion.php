<?php

namespace App\Filament\Resources\SegmentoPoblacionResource\Pages;

use App\Filament\Resources\SegmentoPoblacionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSegmentosPoblacion extends ListRecords
{
    protected static string $resource = SegmentoPoblacionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
