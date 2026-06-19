<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\TipoRelacionProfesionalResource\Pages;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Usuarios\Models\TipoRelacionProfesional;

/**
 * Backoffice: gestión del catálogo de tipos de relación profesional.
 *
 * Accesible en /admin/tipos-relacion-profesional.
 */
class TipoRelacionProfesionalResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = TipoRelacionProfesional::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'Relaciones profesionales';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?string $modelLabel = 'Relación profesional';

    protected static ?string $pluralModelLabel = 'Relaciones profesionales';

    protected static ?int $navigationSort = 10;

    /**
     * Define el formulario de relaciones profesionales.
     *
     * @param Schema $schema Esquema base del formulario.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos de la relación profesional')
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(100),

                    Toggle::make('es_externo')
                        ->label('Es personal externo')
                        ->helperText('Si está activo, el campo "Organización" del profesional será relevante.')
                        ->default(false),

                    Toggle::make('activo')
                        ->label('Activo')
                        ->default(true),
                ]),
        ]);
    }

    /**
     * Configura el listado de relaciones profesionales.
     *
     * @param Table $table Tabla base.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Relación profesional')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('es_externo')
                    ->label('Externo')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('profesionales_count')
                    ->label('Profesionales')
                    ->counts('profesionales')
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')->label('Estado'),
                Tables\Filters\TernaryFilter::make('es_externo')->label('Externo'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('nombre');
    }

    /**
     * Declara las páginas del catálogo de relaciones profesionales.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTiposRelacion::route('/'),
            'create' => Pages\CreateTipoRelacion::route('/create'),
            'edit' => Pages\EditTipoRelacion::route('/{record}/edit'),
        ];
    }
}
