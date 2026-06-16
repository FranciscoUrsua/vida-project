<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\UnidadOrganizativaResource\Pages;
use App\Models\UnidadOrganizativa;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/**
 * Resource Filament para gestionar Unidades Organizativas.
 *
 * Accesible en /admin/unidades-organizativas.
 */
class UnidadOrganizativaResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = UnidadOrganizativa::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Unidades Organizativas';

    protected static string|\UnitEnum|null $navigationGroup = 'Organización';

    protected static ?string $modelLabel = 'Unidad Organizativa';

    protected static ?string $pluralModelLabel = 'Unidades Organizativas';

    protected static ?int $navigationSort = 1;

    /**
     * Configura el formulario de unidades organizativas.
     *
     * @param Schema $schema Schema Filament a configurar.
     * @return Schema Schema configurado.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identificación')
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(200)
                        ->columnSpanFull(),

                    TextInput::make('nombre_corto')
                        ->label('Nombre corto / acrónimo')
                        ->maxLength(40)
                        ->placeholder('p. ej.: CSS Vallecas')
                        ->helperText('Se muestra en badges y cabeceras de expediente.'),

                    Select::make('tipo')
                        ->label('Tipo')
                        ->required()
                        ->options(fn () => DB::table('tipos_unidad_organizativa')
                            ->orderBy('nombre')
                            ->pluck('nombre', 'codigo')
                            ->toArray()),

                    Select::make('parent_id')
                        ->label('UO padre')
                        ->placeholder('— Sin padre (nodo raíz) —')
                        ->options(fn () => UnidadOrganizativa::activas()
                            ->orderBy('nombre')
                            ->pluck('nombre', 'id')
                            ->toArray())
                        ->searchable()
                        ->nullable(),

                    Toggle::make('activa')
                        ->label('Activa')
                        ->default(true),
                ]),

            Section::make('Plan de intervención')
                ->description('Define cómo se llama el plan de intervención en esta unidad organizativa. Si se deja en blanco, se usará "Plan de intervención" / "Plan".')
                ->schema([
                    TextInput::make('plan_nombre_completo')
                        ->label('Nombre completo del plan')
                        ->placeholder('Plan de intervención')
                        ->maxLength(80),

                    TextInput::make('plan_nombre_corto')
                        ->label('Nombre abreviado (acrónimo)')
                        ->placeholder('Plan')
                        ->maxLength(10),
                ]),
        ]);
    }

    /**
     * Configura la tabla de unidades organizativas.
     *
     * @param Table $table Tabla Filament a configurar.
     * @return Table Tabla configurada.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('padre.nombre')
                    ->label('UO padre')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\IconColumn::make('activa')
                    ->label('Activa')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activa')
                    ->label('Estado')
                    ->trueLabel('Solo activas')
                    ->falseLabel('Solo inactivas'),

                Tables\Filters\SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(fn () => DB::table('tipos_unidad_organizativa')
                        ->orderBy('nombre')
                        ->pluck('nombre', 'codigo')
                        ->toArray()),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('nombre');
    }

    /**
     * Devuelve las paginas registradas para el recurso.
     *
     * @return array<string, mixed> Rutas de paginas Filament.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnidadesOrganizativas::route('/'),
            'create' => Pages\CreateUnidadOrganizativa::route('/create'),
            'edit' => Pages\EditUnidadOrganizativa::route('/{record}/edit'),
        ];
    }
}
