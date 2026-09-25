<?php

namespace App\Filament\Resources\PropuestaEliminacionResource\Pages;

use App\Filament\Resources\PropuestaEliminacionResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Documentos\Enums\EstadoPropuestaEliminacion;
use Modules\Documentos\Models\DocumentoVersion;
use Modules\Documentos\Models\PropuestaEliminacion;

/**
 * Página de detalle de una propuesta de eliminación: versiones incluidas, resolución y acta.
 *
 * Solo muestra metadatos (documento, versión, tipo, fecha de captura); nunca el
 * nombre original ni el contenido.
 */
class ViewPropuestaEliminacion extends ViewRecord
{
    protected static string $resource = PropuestaEliminacionResource::class;

    /**
     * Acciones de cabecera: aprobar o rechazar si sigue pendiente.
     *
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            PropuestaEliminacionResource::accionAprobar(),
            PropuestaEliminacionResource::accionRechazar(),
        ];
    }

    /**
     * Esquema de detalle de la propuesta.
     *
     * @param Schema $schema Esquema base.
     *
     * @return Schema
     */
    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Propuesta')
                ->columns(3)
                ->schema([
                    TextEntry::make('estado')
                        ->label('Estado')
                        ->formatStateUsing(fn (EstadoPropuestaEliminacion $state): string => $state->etiqueta()),
                    TextEntry::make('created_at')->label('Propuesta el')->dateTime('d/m/Y H:i'),
                    TextEntry::make('resueltaPor.name')->label('Resuelta por')->placeholder('—'),
                    TextEntry::make('resuelta_en')->label('Resuelta el')->dateTime('d/m/Y H:i')->placeholder('—'),
                    TextEntry::make('acta.numero')->label('Acta')->placeholder('—'),
                    TextEntry::make('observaciones')->label('Observaciones')->placeholder('—'),
                ]),

            Section::make('Versiones propuestas')
                ->schema([
                    TextEntry::make('versiones')
                        ->hiddenLabel()
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->state(fn (PropuestaEliminacion $record): array => $record->versionesPropuestas()
                            ->map(fn (DocumentoVersion $v): string => sprintf(
                                '%s · %s v%d · capturada el %s · %s%s',
                                $v->documento->tipo->nombre,
                                $v->documento->uuid,
                                $v->numero,
                                $v->fecha_captura->format('d/m/Y'),
                                $v->estado->etiqueta(),
                                in_array($v->id, $record->excluidas ?? [], true) ? ' · excluida al aprobar' : '',
                            ))
                            ->all()),
                ]),
        ]);
    }
}
