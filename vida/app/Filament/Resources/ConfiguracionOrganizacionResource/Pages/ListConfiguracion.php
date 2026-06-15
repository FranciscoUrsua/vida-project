<?php

namespace App\Filament\Resources\ConfiguracionOrganizacionResource\Pages;

use App\Filament\Resources\ConfiguracionOrganizacionResource;
use App\Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Modules\Organizacion\Services\ConfiguracionService;

class ListConfiguracion extends ListRecords
{
    protected static string $resource = ConfiguracionOrganizacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('identidad_visual')
                ->label('Identidad visual')
                ->icon('heroicon-o-photo')
                ->form([
                    FileUpload::make('logo_path')
                        ->label('Logotipo de la aplicación')
                        ->disk('public')
                        ->directory('branding')
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'])
                        ->helperText('Se muestra en la barra lateral. Si no se sube ninguno, se muestra el nombre de la aplicación o "VIDA360". Dimensiones recomendadas: 200×60 px.'),

                    TextInput::make('nombre_aplicacion')
                        ->label('Nombre de la aplicación')
                        ->maxLength(60)
                        ->placeholder('VIDA360')
                        ->helperText('Se muestra si no hay logotipo. Si se deja vacío, se muestra "VIDA360".'),
                ])
                ->fillForm(fn (): array => [
                    'nombre_aplicacion' => app(ConfiguracionService::class)->get('nombre_aplicacion', ''),
                ])
                ->action(function (array $data): void {
                    $svc = app(ConfiguracionService::class);

                    if (! empty($data['logo_path'])) {
                        $svc->set('logo_path', $data['logo_path']);
                    }

                    $svc->set('nombre_aplicacion', $data['nombre_aplicacion'] ?? '');
                }),
        ];
    }
}
