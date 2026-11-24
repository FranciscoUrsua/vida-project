<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestionUsuarios;
use App\Filament\Resources\ExcepcionProfesionalResource\Pages;
use App\Support\Ambito\AmbitoGestionUsuarios;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Agenda\Enums\OrigenExcepcion;
use Modules\Agenda\Enums\TipoExcepcion;
use Modules\Agenda\Models\ExcepcionProfesional;
use Modules\Centro\Models\Centro;

/**
 * Recurso Filament para la gestión de excepciones profesionales.
 */
class ExcepcionProfesionalResource extends Resource
{
    use AutorizaGestionUsuarios;

    protected static ?string $model = ExcepcionProfesional::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Excepciones de profesionales';

    protected static string|\UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?string $navigationParentItem = 'Supervisión';

    protected static ?string $modelLabel = 'Excepción';

    protected static ?string $pluralModelLabel = 'Excepciones de profesionales';

    protected static ?int $navigationSort = 60;

    /**
     * Define el formulario de excepciones de profesionales.
     *
     * @param Schema $schema Esquema base del formulario.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Profesional afectado')
                ->columns(2)
                ->schema([
                    Select::make('usuario_id')
                        ->label('Profesional')
                        ->options(fn () => app(AmbitoGestionUsuarios::class)->opcionesUsuarios(auth()->user()))
                        ->searchable()
                        ->required(),

                    Select::make('centro_id')
                        ->label('Centro')
                        ->options(fn () => app(AmbitoGestionUsuarios::class)->opcionesCentros(auth()->user()))
                        ->searchable()
                        ->required(),
                ]),

            Section::make('Tipo y período')
                ->columns(2)
                ->schema([
                    Select::make('tipo')
                        ->label('Tipo de excepción')
                        ->options(collect(TipoExcepcion::cases())->mapWithKeys(
                            fn (TipoExcepcion $t) => [$t->value => $t->label()]
                        ))
                        ->required(),

                    Toggle::make('afecta_disponibilidad')
                        ->label('Afecta disponibilidad')
                        ->default(true)
                        ->helperText('Si está activo, las citas existentes en este período quedarán pendientes de reasignación.'),

                    DatePicker::make('fecha_inicio')
                        ->label('Fecha inicio')
                        ->required(),

                    DatePicker::make('fecha_fin')
                        ->label('Fecha fin')
                        ->required(),
                ]),

            Section::make('Detalle')
                ->schema([
                    Textarea::make('franja_afectada')
                        ->label('Franja afectada (JSON)')
                        ->rows(2)
                        ->nullable()
                        ->helperText(
                            'Solo informar si la excepción es parcial (afecta a una franja del día, no al día completo). '.
                            'Dejar vacío para excepción de día completo. '.
                            'Formato: [{"inicio": "09:00", "fin": "14:00"}]'
                        ),

                    Textarea::make('notas')
                        ->label('Notas')
                        ->rows(2)
                        ->nullable(),

                    // origen es siempre 'manual' al crear; visible solo en edición para trazabilidad
                    Hidden::make('origen')
                        ->default(OrigenExcepcion::Manual->value)
                        ->visibleOn('create'),

                    Select::make('origen')
                        ->label('Origen')
                        ->options(collect(OrigenExcepcion::cases())->mapWithKeys(
                            fn (OrigenExcepcion $o) => [$o->value => $o->label()]
                        ))
                        ->disabled()
                        ->visibleOn('edit'),
                ]),
        ]);
    }

    /**
     * Configura el listado de excepciones de profesionales.
     *
     * @param Table $table Tabla base.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('usuario.name')
                    ->label('Profesional')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('centro.nombre')
                    ->label('Centro')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (TipoExcepcion $state) => $state->label())
                    ->color(fn (TipoExcepcion $state) => match ($state) {
                        TipoExcepcion::BajaMedica => 'danger',
                        TipoExcepcion::Vacaciones => 'info',
                        TipoExcepcion::DiaLibre => 'success',
                        TipoExcepcion::Formacion => 'warning',
                        TipoExcepcion::ReduccionJornada => 'primary',
                        TipoExcepcion::Guardia => 'gray',
                        TipoExcepcion::Otros => 'gray',
                    }),

                Tables\Columns\TextColumn::make('fecha_inicio')
                    ->label('Desde')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('fecha_fin')
                    ->label('Hasta')
                    ->date('d/m/Y'),

                Tables\Columns\IconColumn::make('afecta_disponibilidad')
                    ->label('Afecta disp.')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('origen')
                    ->label('Origen')
                    ->badge()
                    ->formatStateUsing(fn (OrigenExcepcion $state) => $state->label())
                    ->color(fn (OrigenExcepcion $state) => match ($state) {
                        OrigenExcepcion::Manual => 'gray',
                        OrigenExcepcion::ApiRrhh => 'info',
                    }),

                Tables\Columns\TextColumn::make('creadoPor.name')
                    ->label('Creado por')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('centro_id')
                    ->label('Centro')
                    ->relationship('centro', 'nombre'),

                Tables\Filters\SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(collect(TipoExcepcion::cases())->mapWithKeys(
                        fn (TipoExcepcion $t) => [$t->value => $t->label()]
                    )),

                Tables\Filters\SelectFilter::make('usuario_id')
                    ->label('Profesional')
                    ->relationship('usuario', 'name'),

                Tables\Filters\Filter::make('rango_fechas')
                    ->form([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['desde'], fn ($q) => $q->where('fecha_fin', '>=', $data['desde']))
                            ->when($data['hasta'], fn ($q) => $q->where('fecha_inicio', '<=', $data['hasta']));
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('fecha_inicio', 'desc');
    }

    /**
     * Declara las páginas del recurso de excepciones de profesionales.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExcepcionesProfesional::route('/'),
            'create' => Pages\CreateExcepcionProfesional::route('/create'),
            'edit' => Pages\EditExcepcionProfesional::route('/{record}/edit'),
        ];
    }

    /**
     * Listado limitado a los centros del ámbito del usuario (adm_usuarios: su UO y las
     * que dependen de ella; adm_sistema, todos).
     *
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        $usuario = auth()->user();
        $query = parent::getEloquentQuery();

        return $usuario === null
            ? $query->whereRaw('1 = 0')
            : app(AmbitoGestionUsuarios::class)->limitarPorCentro($query, $usuario);
    }

    /**
     * Edición: gestión de usuarios y, además, centro del ámbito.
     *
     * @param Model $record
     *
     * @return bool
     */
    public static function canEdit(Model $record): bool
    {
        return static::gestionaElCentroDe($record);
    }

    /**
     * Borrado: gestión de usuarios y, además, centro del ámbito.
     *
     * @param Model $record
     *
     * @return bool
     */
    public static function canDelete(Model $record): bool
    {
        return static::gestionaElCentroDe($record);
    }

    /**
     * Si el usuario gestiona usuarios y el registro es de un centro de su ámbito.
     *
     * @param Model $record
     *
     * @return bool
     */
    private static function gestionaElCentroDe(Model $record): bool
    {
        $usuario = auth()->user();

        return $usuario !== null
            && $usuario->hasAnyRole(['adm_sistema', 'adm_usuarios'])
            && app(AmbitoGestionUsuarios::class)->centroEnAmbito($usuario, $record->getAttribute('centro_id'));
    }
}
