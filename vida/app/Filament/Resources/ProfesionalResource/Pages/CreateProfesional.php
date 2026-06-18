<?php

namespace App\Filament\Resources\ProfesionalResource\Pages;

use App\Filament\Resources\ProfesionalResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Página de creación de profesionales.
 */
class CreateProfesional extends CreateRecord
{
    protected static string $resource = ProfesionalResource::class;
}
