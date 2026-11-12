<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\CentroResource\Pages;
use App\Filament\Resources\CentroResource\RelationManagers\AmbitosTerritorialesRelationManager;
use App\Filament\Resources\CentroResource\RelationManagers\ColeccionesPlazasRelationManager;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\TipoCentro;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;

/**
 * Recurso Filament para la gestión de centros.
 */
class CentroResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = Centro::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Centros';

    protected static string|\UnitEnum|null $navigationGroup = 'Centros y Servicios';

    protected static ?string $modelLabel = 'Centro';

    protected static ?string $pluralModelLabel = 'Centros';

    protected static ?int $navigationSort = 2;

    /**
     * Formulario de alta y edición de centros, con la configuración de asignación.
     *
     * @param Schema $schema
     * @return Schema
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identificación')
                ->columns(2)
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre completo')
                        ->required()
                        ->maxLength(200),

                    TextInput::make('nombre_corto')
                        ->label('Nombre corto')
                        ->maxLength(60)
                        ->nullable()
                        ->helperText('Abreviatura usada en listados y etiquetas.'),

                    Select::make('tipo_gestion')
                        ->label('Tipo de gestión')
                        ->options([
                            'municipal_directo' => 'Municipal directo',
                            'municipal_concertado' => 'Municipal concertado',
                            'privado_concertado' => 'Privado concertado',
                            'privado_puro' => 'Privado puro',
                        ])
                        ->required()
                        ->default('municipal_directo'),

                    Select::make('unidad_organizativa_id')
                        ->label('Unidad organizativa')
                        ->relationship('unidadOrganizativa', 'nombre')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->placeholder('Sin unidad asignada'),

                    Toggle::make('activo')
                        ->label('Activo')
                        ->default(true),
                ]),

            Section::make('Ubicación')
                ->columns(2)
                ->schema([
                    TextInput::make('direccion')
                        ->label('Dirección')
                        ->maxLength(300)
                        ->nullable()
                        ->columnSpanFull(),

                    TextInput::make('codigo_postal')
                        ->label('Código postal')
                        ->maxLength(10)
                        ->nullable(),

                    TextInput::make('coordenadas')
                        ->label('Coordenadas')
                        ->maxLength(100)
                        ->nullable()
                        ->helperText('Formato: latitud,longitud — ej: 40.416775,-3.703790'),
                ]),

            Section::make('Contacto')
                ->columns(3)
                ->schema([
                    TextInput::make('telefono')
                        ->label('Teléfono')
                        ->tel()
                        ->maxLength(30)
                        ->nullable(),

                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->maxLength(255)
                        ->nullable(),

                    TextInput::make('web')
                        ->label('Web')
                        ->url()
                        ->maxLength(255)
                        ->nullable(),
                ]),

            Section::make('Configuración')
                ->schema([
                    Textarea::make('notas')
                        ->label('Notas internas')
                        ->rows(3)
                        ->nullable(),

                    Textarea::make('protocolo')
                        ->label('Protocolo propio')
                        ->rows(4)
                        ->nullable()
                        ->helperText('Vacío: rige el protocolo del tipo de centro.'),
                ]),

            Section::make('Tipo de centro')
                ->description('El tipo fija la UO funcional, el público, el catálogo y las reglas de asignación. No se cambia: para pasar a otro tipo, da de baja el centro y crea otro con este como antecesor.')
                ->columns(2)
                ->schema([
                    // Inmutable (RN-01): solo se elige al crear; en edición no se envía
                    Select::make('tipo_centro_id')
                        ->label('Tipo de centro')
                        ->options(fn () => TipoCentro::activos()->orderBy('nombre')->pluck('nombre', 'id'))
                        ->required()
                        ->disabledOn('edit')
                        ->dehydrated(fn (string $operation) => $operation === 'create'),

                    Select::make('centro_antecesor_id')
                        ->label('Centro antecesor')
                        ->relationship('antecesor', 'nombre')
                        ->searchable()
                        ->nullable()
                        ->disabledOn('edit')
                        ->dehydrated(fn (string $operation) => $operation === 'create')
                        ->helperText('Centro al que sustituye, si nace de una baja y alta.'),

                    // Propiedades «solo tipo»: en lectura, con el valor del tipo (docs/modulo-tipo-centro.md §4)
                    Placeholder::make('config_adscripcion')
                        ->label('Adscripción de personas')
                        ->content(fn (?Centro $record) => $record === null ? '—'
                            : (app(ConfiguracionEfectivaCentro::class)->inscripcionLibre($record) ? 'Libre elección' : 'Por domicilio')),

                    Placeholder::make('config_referencia')
                        ->label('Profesional de referencia')
                        ->content(fn (?Centro $record) => $record === null ? '—'
                            : app(ConfiguracionEfectivaCentro::class)->modoAsignacionReferencia($record)->label()),

                    Placeholder::make('config_reparto')
                        ->label('Reparto')
                        ->content(fn (?Centro $record) => $record === null ? '—' : sprintf(
                            'Ventana de %d meses · caso dormido a los %d meses sin apuntes',
                            app(ConfiguracionEfectivaCentro::class)->ventanaRepartoMeses($record),
                            app(ConfiguracionEfectivaCentro::class)->mesesInactividadCaso($record),
                        )),

                    Placeholder::make('config_prestaciones')
                        ->label('Prestaciones')
                        ->content(fn (?Centro $record) => $record === null ? '—'
                            : app(ConfiguracionEfectivaCentro::class)->prestaciones($record)->count().' del catálogo del tipo'),

                    Placeholder::make('config_segmentos')
                        ->label('Público destinatario')
                        ->content(fn (?Centro $record) => $record === null ? '—'
                            : (app(ConfiguracionEfectivaCentro::class)->segmentos($record)->pluck('nombre')->implode(', ') ?: '—')),
                ]),

            Section::make('Vigencia')
                ->columns(2)
                ->schema([
                    DatePicker::make('fecha_alta')
                        ->label('Fecha de alta')
                        ->required()
                        ->default(now()),

                    DatePicker::make('fecha_baja')
                        ->label('Fecha de cierre')
                        ->nullable()
                        ->helperText('Solo informar si el centro ha cerrado definitivamente.'),
                ]),

        ]);
    }

    /**
     * Listado de centros.
     *
     * @param Table $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre_corto')
                    ->label('Nombre')
                    ->description(fn (Centro $record) => $record->nombre)
                    ->searchable(['nombre', 'nombre_corto'])
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('nombre', $direction)),

                Tables\Columns\TextColumn::make('tipoCentro.nombre')
                    ->label('Tipo de centro')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('tipo_gestion')
                    ->label('Tipo de gestión')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'municipal_directo' => 'primary',
                        'municipal_concertado' => 'info',
                        'privado_concertado' => 'warning',
                        'privado_puro' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'municipal_directo' => 'Municipal directo',
                        'municipal_concertado' => 'Municipal concertado',
                        'privado_concertado' => 'Privado concertado',
                        'privado_puro' => 'Privado puro',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('unidadOrganizativa.nombre')
                    ->label('Unidad organizativa')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo_gestion')
                    ->label('Tipo de gestión')
                    ->options([
                        'municipal_directo' => 'Municipal directo',
                        'municipal_concertado' => 'Municipal concertado',
                        'privado_concertado' => 'Privado concertado',
                        'privado_puro' => 'Privado puro',
                    ]),

                Tables\Filters\TernaryFilter::make('activo')->label('Estado'),
            ])
            ->actions([
                // Sin borrado: los centros se dan de baja (RN-11)
                EditAction::make(),
            ])
            ->defaultSort('nombre');
    }

    /**
     * Cualquier usuario autenticado del backoffice puede ver los centros.
     *
     * @return bool
     */
    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    /**
     * Editar exige el permiso centro.gestionar.
     *
     * @param Model $record
     * @return bool
     */
    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('centro.gestionar') ?? false;
    }

    /**
     * Borrar exige el mismo permiso que editar.
     *
     * @param Model $record
     * @return bool
     */
    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    /**
     * Define los relation managers del recurso de centros.
     *
     * @return array<int, class-string>
     */
    public static function getRelationManagers(): array
    {
        return [
            AmbitosTerritorialesRelationManager::class,
            ColeccionesPlazasRelationManager::class,
        ];
    }

    /**
     * Páginas del recurso.
     *
     * @return array<string, \Filament\Resources\Pages\PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCentros::route('/'),
            'create' => Pages\CreateCentro::route('/create'),
            'edit' => Pages\EditCentro::route('/{record}/edit'),
        ];
    }
}
