<?php

namespace App\Filament\Resources\DocumentoResource\Pages;

use App\Filament\Resources\DocumentoResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Services\LecturaDocumentoService;

/**
 * Página de detalle de documentos.
 */
class ViewDocumento extends ViewRecord
{
    protected static string $resource = DocumentoResource::class;

    /**
     * Acciones de cabecera: ver el PDF y verificar su integridad.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        /** @var Documento $documento */
        $documento = $this->record;

        return [
            Action::make('ver_fichero')
                ->label('Ver PDF')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => app(LecturaDocumentoService::class)->urlTemporal($documento, 60))
                ->openUrlInNewTab(),

            Action::make('verificar_integridad')
                ->label('Verificar integridad')
                ->icon('heroicon-o-shield-check')
                ->color('info')
                ->action(function (Action $action) use ($documento): void {
                    $version = $documento->versionVigente;
                    $ok = $version !== null && app(LecturaDocumentoService::class)->verificarIntegridad($version);
                    if ($ok) {
                        $action->successNotificationTitle('Integridad verificada: el fichero no ha sido alterado.');
                        $action->sendSuccessNotification();
                    } else {
                        $action->failureNotificationTitle('¡Alerta de integridad! El fichero falta, no se puede descifrar o su hash no coincide.');
                        $action->sendFailureNotification();
                    }
                }),
        ];
    }
}
