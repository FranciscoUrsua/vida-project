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
     * Configura el formulario de lineas del cuadrante.
     *
     * @param Schema $schema Schema Filament a configurar.
     * @return Schema Schema configurado.
     */
    public function form(Schema $schema): Schema
    {
        // Solo lectura en esta fase; la edición de líneas se implementará con Livewire.
        return $schema->components([]);
    }

    /**
     * Configura la tabla de lineas del cuadrante.
     *
     * @param Table $table Tabla Filament a configurar.
     * @return Table Tabla configurada.
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
