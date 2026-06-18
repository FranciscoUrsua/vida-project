<?php

namespace App\Filament\Resources\CentroResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ColeccionesPlazasRelationManager extends RelationManager
{
    protected static string $relationship = 'coleccionesPlazas';

    protected static ?string $title = 'Colecciones de plazas';

    /**
     * Define el formulario del relation manager de colecciones de plazas.
     *
     * @param Schema $schema Esquema base.
     *
     * @return Schema
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos de la colección')
                ->columns(2)
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(200)
                        ->columnSpanFull(),

                    Select::make('tipo_plaza')
                        ->label('Tipo de plaza')
                        ->options([
                            'pernocta' => 'Pernocta (alojamiento nocturno)',
                            'dia' => 'Día (atención diurna)',
                        ])
                        ->required()
                        ->default('pernocta'),

                    Select::make('modo_acceso')
                        ->label('Modo de acceso')
                        ->options([
                            'libre' => 'Libre',
                            'prescripcion_directa' => 'Prescripción directa',
                            'prescripcion_lista_espera' => 'Prescripción con lista de espera',
                        ])
                        ->required()
                        ->default('prescripcion_directa'),

                    TextInput::make('capacidad')
                        ->label('Capacidad total')
                        ->numeric()
                        ->minValue(1)
                        ->required()
                        ->helperText('Número total de plazas en esta colección.'),

                    Toggle::make('activa')
                        ->label('Activa')
                        ->default(true),

                    Textarea::make('notas')
                        ->label('Notas')
                        ->rows(2)
                        ->nullable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * Define la tabla del relation manager de colecciones de plazas.
     *
     * @param Table $table Tabla base.
     *
     * @return Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable(),

                Tables\Columns\TextColumn::make('tipo_plaza')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pernocta' => 'Pernocta',
                        'dia' => 'Día',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('modo_acceso')
                    ->label('Acceso')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'libre' => 'Libre',
                        'prescripcion_directa' => 'Prescripción directa',
                        'prescripcion_lista_espera' => 'Con lista de espera',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('capacidad')
                    ->label('Capacidad')
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('activa')
                    ->label('Activa')
                    ->boolean()
                    ->alignCenter(),
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
