<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\TipoActividadResource\Pages;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Centro\Models\TipoActividad;

/**
 * Recurso Filament para la gestión de tipos de actividad.
 */
class TipoActividadResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = TipoActividad::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Tipos de actividad';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?string $modelLabel = 'Tipo de actividad';

    protected static ?string $pluralModelLabel = 'Tipos de actividad';

    protected static ?int $navigationSort = 5;

    /**
     * Define el formulario de tipos de actividad.
     *
     * @param Schema $schema Esquema base del formulario.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos del tipo de actividad')
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(150),

                    TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(100)
                        ->helperText('Identificador estable en minúsculas con guiones. Ej: grupo-apoyo'),

                    Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(2)
                        ->nullable(),

                    Toggle::make('activo')
                        ->label('Activo')
                        ->default(true),
                ]),
        ]);
    }

    /**
     * Configura el listado de tipos de actividad.
     *
     * @param Table $table Tabla base.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')->label('Estado'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('nombre');
    }

    /**
     * Declara las páginas del catálogo de tipos de actividad.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTiposActividad::route('/'),
            'create' => Pages\CreateTipoActividad::route('/create'),
            'edit' => Pages\EditTipoActividad::route('/{record}/edit'),
        ];
    }
}
