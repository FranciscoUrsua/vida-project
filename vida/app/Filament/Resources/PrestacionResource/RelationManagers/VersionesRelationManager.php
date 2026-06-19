<?php

namespace App\Filament\Resources\PrestacionResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Historial de versiones de una prestación.
 *
 * Muestra las versiones registradas en la tabla `versiones` (polimórfico).
 * Cada versión contiene el snapshot completo del estado anterior al cambio.
 * Solo lectura: el historial no se puede editar ni eliminar.
 */
class VersionesRelationManager extends RelationManager
{
    protected static string $relationship = 'versiones';

    protected static ?string $title = 'Historial de versiones';

    /**
     * Configura el historial de versiones de la prestación.
     *
     * @param Table $table Tabla base.
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha del cambio')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('usuario.name')
                    ->label('Modificado por')
                    ->default('Sistema'),

                Tables\Columns\TextColumn::make('motivo')
                    ->label('Motivo')
                    ->default('—')
                    ->limit(80),

                Tables\Columns\TextColumn::make('datos')
                    ->label('Campos guardados')
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state).' campos' : '—')
                    ->alignCenter(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('ver_snapshot')
                    ->label('Ver snapshot')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Snapshot de la versión')
                    ->modalDescription('Estado completo de la prestación en el momento del cambio.')
                    ->modalContent(fn ($record) => view('filament.prestaciones.snapshot-modal', [
                        'datos' => $record->datos,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
            ])
            ->headerActions([])
            ->bulkActions([]);
    }

    /**
     * Indica que el relation manager es de solo lectura.
     */
    public function isReadOnly(): bool
    {
        return true;
    }
}
