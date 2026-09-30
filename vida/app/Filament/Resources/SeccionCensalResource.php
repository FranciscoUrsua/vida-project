<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\SeccionCensalResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Organizacion\Models\SeccionCensal;

/**
 * Backoffice: consulta del catálogo de secciones censales.
 *
 * Solo lectura: el catálogo se carga de los datos oficiales
 * (CargaUnidadesTerritoriales). Lo único editable es si la sección está activa.
 */
class SeccionCensalResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = SeccionCensal::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Secciones censales';

    protected static string|\UnitEnum|null $navigationGroup = 'Organización';

    protected static ?string $modelLabel = 'Sección censal';

    protected static ?string $pluralModelLabel = 'Secciones censales';

    protected static ?int $navigationSort = 4;

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
     * Las secciones no se borran: se desactivan.
     *
     * @param Model $record Registro objetivo.
     * @return bool
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * Configura el listado de secciones censales.
     *
     * @param Table $table Tabla base.
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('codigo_ine')
                    ->label('Código INE')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('distrito.nombre')
                    ->label('Distrito')
                    ->sortable(),

                Tables\Columns\TextColumn::make('barrio.nombre')
                    ->label('Barrio')
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('activa')
                    ->label('Activa')
                    ->disabled(fn () => ! static::canViewAny())
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('distrito_id')
                    ->label('Distrito')
                    ->relationship('distrito', 'nombre'),
                Tables\Filters\SelectFilter::make('barrio_id')
                    ->label('Barrio')
                    ->relationship('barrio', 'nombre')
                    ->searchable(),
                Tables\Filters\TernaryFilter::make('activa')->label('Estado'),
            ])
            ->defaultSort('codigo_ine');
    }

    /**
     * Declara las páginas del catálogo de secciones censales.
     *
     * @return array<string, \Filament\Resources\Pages\PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSeccionesCensales::route('/'),
        ];
    }
}
