<?php

namespace App\Filament\Resources\CentroResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Centro\Models\AmbitoTerritorial;

/**
 * Gestor de relación de ámbitos territoriales de un centro.
 */
class AmbitosTerritorialesRelationManager extends RelationManager
{
    protected static string $relationship = 'ambitosTeritoriales';

    protected static ?string $title = 'Ámbito territorial';

    /**
     * Define el formulario del relation manager de ámbitos territoriales.
     *
     * @param Schema $schema Esquema base.
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ámbito territorial')
                ->columns(2)
                ->schema([
                    Select::make('tipo')
                        ->label('Tipo')
                        ->options(array_combine(
                            AmbitoTerritorial::TIPOS,
                            array_map(fn ($t) => match ($t) {
                                'ciudad_completa' => 'Ciudad completa',
                                'demarcacion_oficial' => 'Demarcación oficial',
                                'barrios' => 'Barrios',
                                'secciones_censales' => 'Secciones censales',
                                'poligono_gis' => 'Polígono GIS',
                                default => ucfirst($t),
                            }, AmbitoTerritorial::TIPOS)
                        ))
                        ->required(),

                    TextInput::make('descripcion')
                        ->label('Descripción')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Ej: "Distrito de Vallecas", "Barrio de Lavapiés"'),

                    TextInput::make('referencia_id')
                        ->label('ID de referencia')
                        ->numeric()
                        ->nullable(),

                    TextInput::make('referencia_tipo')
                        ->label('Tipo de referencia')
                        ->nullable()
                        ->helperText('Ej: Distrito, Barrio'),

                    Textarea::make('geojson')
                        ->label('GeoJSON')
                        ->nullable()
                        ->columnSpanFull()
                        ->helperText('Solo para tipo "Polígono GIS". JSON con la geometría del polígono.'),
                ]),
        ]);
    }

    /**
     * Define la tabla del relation manager de ámbitos territoriales.
     *
     * @param Table $table Tabla base.
     */
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'ciudad_completa' => 'Ciudad completa',
                        'demarcacion_oficial' => 'Demarcación oficial',
                        'barrios' => 'Barrios',
                        'secciones_censales' => 'Secciones censales',
                        'poligono_gis' => 'Polígono GIS',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->searchable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
