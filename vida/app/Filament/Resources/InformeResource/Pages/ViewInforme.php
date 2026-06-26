<?php

namespace App\Filament\Resources\InformeResource\Pages;

use App\Filament\Resources\InformeResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Modules\Documentos\Models\Informe;
use Modules\Documentos\Services\ServicioAlmacenamiento;
use Modules\Documentos\Services\ServicioFirmaInforme;

/**
 * Página de detalle de informes.
 */
class ViewInforme extends ViewRecord
{
    protected static string $resource = InformeResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Informe $informe */
        $informe = $this->record;

        return [
            Action::make('ver_pdf')
                ->label('Ver PDF firmado')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => $informe->estaFirmado() && $informe->documento_id !== null)
                ->url(function () use ($informe): ?string {
                    $doc = $informe->documento;

                    if ($doc === null) {
                        return null;
                    }

                    return app(ServicioAlmacenamiento::class)->urlTemporal($doc, 60);
                })
                ->openUrlInNewTab(),

            Action::make('anular')
                ->label('Anular informe')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $informe->estaFirmado())
                ->form([
                    Textarea::make('motivo')
                        ->label('Motivo de anulación')
                        ->required()
                        ->minLength(10)
                        ->rows(3),
                ])
                ->action(function (array $data, Action $action) use ($informe): void {
                    try {
                        app(ServicioFirmaInforme::class)->anular(
                            $informe,
                            auth()->id(),
                            $data['motivo']
                        );
                        $this->refreshFormData(['estado', 'motivo_anulacion', 'anulado_en']);
                        $action->successNotificationTitle('Informe anulado correctamente.');
                        $action->sendSuccessNotification();
                    } catch (\DomainException $e) {
                        $action->failureNotificationTitle($e->getMessage());
                        $action->sendFailureNotification();
                    }
                }),
        ];
    }
}
