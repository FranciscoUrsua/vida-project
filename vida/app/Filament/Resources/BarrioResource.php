<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\BarrioResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Organizacion\Models\Barrio;

/**
 * Backoffice: consulta del catálogo de barrios.
 *
 * Solo lectura: el catálogo se carga de los datos oficiales
 * (CargaUnidadesTerritoriales). Lo único editable es si el barrio está activo.
 */
class BarrioResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = Barrio::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Barrios';

    protected static string|\UnitEnum|null $navigationGroup = 'Organización';

    protected static ?string $modelLabel = 'Barrio';

    protected static ?string $pluralModelLabel = 'Barrios';

    protected static ?int $navigationSort = 3;

    /**
     * El catálogo no se da de alta a mano.
     *
     * @return bool
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * El catálogo no se edita a mano (salvo activar/desactivar desde el listado).
     *
     * @param Model $record Registro objetivo.
     * @return bool
     */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /**
     * Los barrios no se borran: se desactivan.
     *
     * @param Model $record Registro objetivo.
     * @return bool
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * Configura el listado de barrios.
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

                Tables\Columns\TextColumn::make('distrito.nombre')
                    ->label('Distrito')
                    ->sortable(),

                Tables\Columns\TextColumn::make('secciones_count')
                    ->label('Secciones')
                    ->counts('secciones')
                    ->alignCenter(),

                Tables\Columns\ToggleColumn::make('activo')
                    ->label('Activo')
                    ->disabled(fn () => ! static::canViewAny())
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('distrito_id')
                    ->label('Distrito')
                    ->relationship('distrito', 'nombre'),
                Tables\Filters\TernaryFilter::make('activo')->label('Estado'),
            ])
            ->defaultSort('codigo');
    }

    /**
     * Declara las páginas del catálogo de barrios.
     *
     * @return array<string, \Filament\Resources\Pages\PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBarrios::route('/'),
        ];
    }
}
