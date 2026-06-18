<?php

namespace App\Filament\Resources\CuadranteMesResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Agenda\Models\LineaCuadrante;

class LineasCuadranteRelationManager extends RelationManager
{
    protected static string $relationship = 'lineas';

    protected static ?string $title = 'Líneas del cuadrante';

    /**
     * Define el formulario del relation manager en modo solo lectura.
     *
     * @param Schema $schema Esquema base del formulario.
     * @return Schema
     */
    public function form(Schema $schema): Schema
    {
        // Solo lectura en esta fase; la edición de líneas se implementará con Livewire.
        return $schema->components([]);
    }

    /**
     * Configura el listado de líneas del cuadrante.
     *
     * @param Table $table Tabla base.
     * @return Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('usuario.name')
                    ->label('Profesional')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('franjas')
                    ->label('Franjas')
                    ->getStateUsing(function (LineaCuadrante $record) {
                        if (empty($record->franjas)) {
                            return '—';
                        }

                        return collect($record->franjas)
                            ->map(fn ($f) => "{$f['inicio']}–{$f['fin']}")
                            ->implode(', ');
                    }),

                Tables\Columns\IconColumn::make('anulada')
                    ->label('Anulada')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->defaultSort('fecha')
            ->headerActions([])
            ->actions([]);
    }
}
