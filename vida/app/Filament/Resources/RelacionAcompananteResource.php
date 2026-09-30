<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RelacionAcompananteResource\Pages;
use App\Models\CatalogoSistema;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Backoffice: relaciones de un acompañante con la persona citada
 * (`catalogos_sistema`, grupo `cita.relacion_acompanante`, docs/modulo-citas.md §2.5).
 *
 * Es un catálogo sin lógica de negocio: el código no decide con sus valores.
 * Solo adm_sistema. No se borran valores: se desactivan, para no dejar
 * acompañantes registrados sin etiqueta.
 */
class RelacionAcompananteResource extends Resource
{
    /** Grupo del catálogo. */
    public const GRUPO = 'cita.relacion_acompanante';

    protected static ?string $model = CatalogoSistema::class;

    protected static ?string $slug = 'relaciones-acompanante';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Relaciones de acompañante';

    protected static string|\UnitEnum|null $navigationGroup = 'Agenda — Configuración';

    protected static ?string $modelLabel = 'Relación de acompañante';

    protected static ?string $pluralModelLabel = 'Relaciones de acompañante';

    protected static ?int $navigationSort = 3;

    /**
     * Formulario de un valor del catálogo.
     *
     * @param Schema $schema
     * @return Schema
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('clave')
                ->label('Clave')
                ->required()
                ->maxLength(50)
                ->regex('/^[a-z][a-z0-9_]*$/')
                ->disabledOn('edit'),
            TextInput::make('etiqueta')->label('Etiqueta')->required()->maxLength(100),
            TextInput::make('orden')->label('Orden')->numeric()->integer()->default(0),
            Toggle::make('activo')->label('Activo')->default(true),
        ]);
    }

    /**
     * Listado del catálogo.
     *
     * @param Table $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('clave')->label('Clave')->fontFamily('mono'),
                TextColumn::make('etiqueta')->label('Etiqueta'),
                TextColumn::make('orden')->label('Orden')->sortable(),
                IconColumn::make('activo')->label('Activo')->boolean(),
            ])
            ->actions([EditAction::make()])
            ->defaultSort('orden');
    }

    /**
     * Solo los valores del grupo.
     *
     * @return Builder<CatalogoSistema>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('grupo', self::GRUPO);
    }

    /**
     * Páginas del recurso.
     *
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRelacionesAcompanante::route('/'),
            'create' => Pages\CreateRelacionAcompanante::route('/create'),
            'edit' => Pages\EditRelacionAcompanante::route('/{record}/edit'),
        ];
    }

    /**
     * Solo adm_sistema.
     *
     * @return bool
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('adm_sistema') ?? false;
    }

    /**
     * Los valores no se borran: se desactivan.
     *
     * @param Model $record
     * @return bool
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
