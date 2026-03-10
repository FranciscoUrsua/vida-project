<?php

namespace App\Filament\Resources\SocialUserResource\Pages;

use App\Filament\Resources\SocialUserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSocialUser extends EditRecord
{
    protected static string $resource = SocialUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
