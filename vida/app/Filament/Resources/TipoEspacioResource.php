<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\TipoEspacioResource\Pages;
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
use Modules\Centro\Models\TipoEspacio;

/**
 * Recurso Filament para la gestión de tipos de espacio.
 */
/**
 * Recurso Filament para la gestión de tipos de espacio.
 */
class TipoEspacioResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = TipoEspacio::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Tipos de espacio';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?string $modelLabel = 'Tipo de espacio';

    protected static ?string $pluralModelLabel = 'Tipos de espacio';

    protected static ?int $navigationSort = 4;

    /**
     * Define el formulario de tipos de espacio.
     *
     * @param Schema $schema Esquema base del formulario.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos del tipo de espacio')
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(150),

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
     * Configura el listado de tipos de espacio.
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
     * Declara las páginas del catálogo de tipos de espacio.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTiposEspacio::route('/'),
            'create' => Pages\CreateTipoEspacio::route('/create'),
            'edit' => Pages\EditTipoEspacio::route('/{record}/edit'),
        ];
    }
}
