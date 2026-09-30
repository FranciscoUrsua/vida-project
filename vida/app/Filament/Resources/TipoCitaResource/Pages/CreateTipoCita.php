<?php

namespace App\Filament\Resources\TipoCitaResource\Pages;

use App\Filament\Resources\TipoCitaResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Página de creación de tipos de cita.
 */
class CreateTipoCita extends CreateRecord
{
    protected static string $resource = TipoCitaResource::class;
}
