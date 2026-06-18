<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\TitulacionResource\Pages;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Usuarios\Models\Titulacion;

/**
 * Backoffice: gestión del catálogo de titulaciones académicas.
 *
 * Accesible en /admin/titulaciones.
 */
class TitulacionResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = Titulacion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Titulaciones';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?string $modelLabel = 'Titulación';

    protected static ?string $pluralModelLabel = 'Titulaciones';

    protected static ?int $navigationSort = 7;

    /**
     * Define el formulario de titulaciones.
     *
     * @param Schema $schema Esquema base del formulario.
     * @return Schema
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos de la titulación')
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre de la titulación')
                        ->required()
                        ->maxLength(200),

                    Toggle::make('activo')
                        ->label('Activa')
                        ->default(true),
                ]),
        ]);
    }

    /**
     * Configura el listado de titulaciones.
     *
     * @param Table $table Tabla base.
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Titulación')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('profesionales_count')
                    ->label('Profesionales')
                    ->counts('profesionales')
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activa')
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
     * Declara las páginas del catálogo de titulaciones.
     *
     * @return array
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTitulaciones::route('/'),
            'create' => Pages\CreateTitulacion::route('/create'),
            'edit' => Pages\EditTitulacion::route('/{record}/edit'),
        ];
    }
}
