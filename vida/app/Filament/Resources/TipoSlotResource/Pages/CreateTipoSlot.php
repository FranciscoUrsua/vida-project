<?php

namespace App\Filament\Resources\TipoSlotResource\Pages;

use App\Filament\Resources\TipoSlotResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Página de creación de tipo de slot del catálogo global.
 */
class CreateTipoSlot extends CreateRecord
{
    protected static string $resource = TipoSlotResource::class;
}
