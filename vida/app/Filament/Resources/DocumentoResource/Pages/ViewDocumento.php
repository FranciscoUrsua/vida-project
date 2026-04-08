<?php

namespace App\Filament\Resources\DocumentoResource\Pages;

use App\Filament\Resources\DocumentoResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Services\ServicioAlmacenamiento;

class ViewDocumento extends ViewRecord
{
    protected static string $resource = DocumentoResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Documento $documento */
        $documento = $this->record;

        return [
            Action::make('ver_fichero')
                ->label('Ver PDF')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => app(ServicioAlmacenamiento::class)->urlTemporal($documento, 60))
                ->openUrlInNewTab(),

            Action::make('verificar_integridad')
                ->label('Verificar integridad')
                ->icon('heroicon-o-shield-check')
                ->color('info')
                ->action(function (Action $action) use ($documento): void {
                    $ok = app(ServicioAlmacenamiento::class)->verificarIntegridad($documento);
                    if ($ok) {
                        $action->successNotificationTitle('Integridad verificada: el fichero no ha sido alterado.');
                        $action->sendSuccessNotification();
                    } else {
                        $action->failureNotificationTitle('¡Alerta de integridad! El hash SHA-256 no coincide.');
                        $action->sendFailureNotification();
                    }
                }),
        ];
    }
}
