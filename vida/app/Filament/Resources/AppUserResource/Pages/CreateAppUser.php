<?php

namespace App\Filament\Resources\AppUserResource\Pages;

use App\Filament\Resources\AppUserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAppUser extends CreateRecord
{
    protected static string $resource = AppUserResource::class;
}
