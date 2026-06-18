<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\DistritoResource\Pages;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Organizacion\Models\Distrito;

/**
 * Backoffice: gestión del catálogo de distritos municipales.
 *
 * Accesible en /admin/distritos.
 */
class DistritoResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = Distrito::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Distritos';

    protected static string|\UnitEnum|null $navigationGroup = 'Organización';

    protected static ?string $modelLabel = 'Distrito';

    protected static ?string $pluralModelLabel = 'Distritos';

    protected static ?int $navigationSort = 2;

    /**
     * Define el formulario de distritos.
     *
     * @param Schema $schema Esquema base del formulario.
     * @return Schema
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos del distrito')
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('codigo')
                        ->label('Código')
                        ->required()
                        ->maxLength(10),

                    TextInput::make('latitud')
                        ->label('Latitud')
                        ->numeric(),

                    TextInput::make('longitud')
                        ->label('Longitud')
                        ->numeric(),

                    Toggle::make('activo')
                        ->label('Activo')
                        ->default(true),
                ]),
        ]);
    }

    /**
     * Configura el listado de distritos.
     *
     * @param Table $table Tabla base.
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('zonas_count')
                    ->label('Zonas')
                    ->counts('zonas')
                    ->alignCenter(),

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
            ->defaultSort('codigo');
    }

    /**
     * Declara las páginas del catálogo de distritos.
     *
     * @return array
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDistritos::route('/'),
            'create' => Pages\CreateDistrito::route('/create'),
            'edit' => Pages\EditDistrito::route('/{record}/edit'),
        ];
    }
}
