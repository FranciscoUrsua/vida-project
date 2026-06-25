<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\SalaResource\Pages;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\Sala;

/**
 * Recurso Filament para la gestión de salas funcionales de los centros.
 */
class SalaResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = Sala::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationLabel = 'Salas';

    protected static string|\UnitEnum|null $navigationGroup = 'Centros y Servicios';

    protected static ?string $modelLabel = 'Sala';

    protected static ?string $pluralModelLabel = 'Salas';

    protected static ?int $navigationSort = 5;

    /**
     * Define el formulario de salas.
     *
     * @param Schema $schema Esquema base del formulario.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos de la sala')
                ->schema([
                    Select::make('centro_id')
                        ->label('Centro')
                        ->options(fn () => Centro::activos()->orderBy('nombre')->pluck('nombre', 'id'))
                        ->searchable()
                        ->required(),

                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('capacidad')
                        ->label('Capacidad (personas)')
                        ->numeric()
                        ->minValue(1)
                        ->nullable()
                        ->helperText('Número de personas. Dejar vacío si no aplica.'),

                    Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(2)
                        ->nullable(),

                    Toggle::make('accesible')
                        ->label('Accesible para personas con movilidad reducida'),

                    Toggle::make('activa')
                        ->label('Activa')
                        ->default(true),

                    Textarea::make('notas')
                        ->label('Notas')
                        ->rows(2)
                        ->nullable(),
                ]),
        ]);
    }

    /**
     * Configura el listado de salas.
     *
     * @param Table $table Tabla base.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('centro.nombre')
                    ->label('Centro')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('nombre')
                    ->label('Sala')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('capacidad')
                    ->label('Capacidad')
                    ->alignCenter()
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('accesible')
                    ->label('Accesible')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('activa')
                    ->label('Activa')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('centro_id')
                    ->label('Centro')
                    ->options(fn () => Centro::activos()->orderBy('nombre')->pluck('nombre', 'id')),

                Tables\Filters\TernaryFilter::make('activa')->label('Estado'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('centro.nombre');
    }

    /**
     * Declara las páginas del recurso de salas.
     */
    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSalas::route('/'),
            'create' => Pages\CreateSala::route('/create'),
            'edit'   => Pages\EditSala::route('/{record}/edit'),
        ];
    }
}
