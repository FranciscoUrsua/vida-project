<?php

namespace App\Filament\Resources;

use App\Filament\Acciones\CentroTipoAcciones;
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
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Centro\Enums\ElementoCatalogo;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\ExclusionCatalogo;
use Modules\Centro\Models\TipoCentro;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;
use Modules\Intervencion\Models\TipoFicha;

/**
 * Recurso Filament para la gestión de centros (docs/modulo-tipo-centro.md §3, §4 y §9).
 *
 * El administrador da de alta, renombra y da de baja; el director de un centro
 * edita solo contacto, notas y protocolo (el resto aparece deshabilitado) y
 * personaliza horario, cierres y exclusiones. Lo que es del tipo se muestra en
 * lectura con el valor del tipo. Los límites se aplican en el servidor
 * (CentroService y CentroPolicy); el formulario solo los refleja.
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
     *
     * @return Schema
     */
    public static function form(Schema $schema): Schema
    {
        // Lo que no es de la dirección del centro (§9), deshabilitado para quien no administra
        $soloAdmin = fn (): bool => ! self::administra();
        $config = fn (): ConfiguracionEfectivaCentro => app(ConfiguracionEfectivaCentro::class);

        return $schema->components([
            Section::make('Identificación')
                ->columns(2)
                ->schema([
                    // La denominación se fija al alta; después se programa con fecha (§8.1)
                    TextInput::make('nombre')
                        ->label('Nombre completo')
                        ->required()
                        ->maxLength(200)
                        ->disabledOn('edit')
                        ->dehydrated(fn (string $operation) => $operation === 'create')
                        ->helperText(fn (string $operation) => $operation === 'edit' ? 'Para cambiarlo, «Programar nueva denominación».' : null),

                    TextInput::make('nombre_corto')
                        ->label('Nombre corto')
                        ->maxLength(60)
                        ->nullable()
                        ->disabledOn('edit')
                        ->dehydrated(fn (string $operation) => $operation === 'create')
                        ->helperText('Abreviatura usada en listados y etiquetas.'),

                    Placeholder::make('historial_denominaciones')
                        ->label('Historial de denominaciones')
                        ->content(fn (?Centro $record) => CentroTipoAcciones::historialDenominaciones($record))
                        ->columnSpanFull()
                        ->visibleOn('edit'),

                    Select::make('tipo_gestion')
                        ->label('Tipo de gestión')
                        ->options([
                            'municipal_directo' => 'Municipal directo',
                            'municipal_concertado' => 'Municipal concertado',
                            'privado_concertado' => 'Privado concertado',
                            'privado_puro' => 'Privado puro',
                        ])
                        ->required()
                        ->default('municipal_directo')
                        ->disabled($soloAdmin),

                    Select::make('unidad_organizativa_id')
                        ->label('Unidad organizativa')
                        ->relationship('unidadOrganizativa', 'nombre')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->placeholder('Sin unidad asignada')
                        ->disabled($soloAdmin),
                ]),

            Section::make('Ubicación')
                ->columns(2)
                ->schema([
                    TextInput::make('direccion')
                        ->label('Dirección')
                        ->maxLength(300)
                        ->nullable()
                        ->disabled($soloAdmin)
                        ->columnSpanFull(),

                    TextInput::make('codigo_postal')
                        ->label('Código postal')
                        ->maxLength(10)
                        ->nullable()
                        ->disabled($soloAdmin),

                    TextInput::make('coordenadas')
                        ->label('Coordenadas')
                        ->maxLength(100)
                        ->nullable()
                        ->disabled($soloAdmin)
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

                    // Sobrescribible (§4): vacío, rige el del tipo
                    Textarea::make('protocolo')
                        ->label('Protocolo propio')
                        ->rows(4)
                        ->nullable()
                        ->hint(fn (?Centro $record) => $record === null ? null : self::insigniaOrigen($config()->origen($record, 'protocolo')))
                        ->hintColor(fn (?Centro $record) => $record !== null && $config()->origen($record, 'protocolo') === 'centro' ? 'warning' : 'gray')
                        ->helperText('Vacío: rige el protocolo del tipo de centro. «Volver al protocolo del tipo» lo vacía.'),

                    Placeholder::make('protocolo_tipo')
                        ->label('Protocolo del tipo')
                        ->content(fn (?Centro $record) => $record === null ? '—' : ($config()->tipo($record)->protocolo ?: '—'))
                        ->visibleOn('edit'),
                ]),

            Section::make('Horario y calendario')
                ->columns(2)
                ->schema([
                    Placeholder::make('horario_efectivo')
                        ->label('Horario')
                        ->hint(fn (?Centro $record) => $record === null ? null : self::insigniaOrigen($config()->origen($record, 'horario')))
                        ->hintColor(fn (?Centro $record) => $record !== null && $config()->origen($record, 'horario') === 'centro' ? 'warning' : 'gray')
                        ->content(fn (?Centro $record) => self::horarioEfectivo($record))
                        ->columnSpanFull(),
                    Placeholder::make('cierres_tipo')
                        ->label('Cierres comunes del tipo')
                        ->content(fn (?Centro $record) => CentroTipoAcciones::cierres($record?->tipoCentro))
                        ->helperText('Se cambian en el tipo de centro.'),
                    Placeholder::make('cierres_centro')
                        ->label('Cierres propios')
                        ->content(fn (?Centro $record) => CentroTipoAcciones::cierres($record))
                        ->helperText('Se suman a los del tipo y prevalecen sobre ellos. Para añadir uno, «Añadir cierre».'),
                ])
                ->visibleOn('edit'),

            Section::make('Exclusiones de catálogo')
                ->description('El tipo permite que este centro no ofrezca parte de su catálogo (RN-08). Para cambiarlas, «Exclusiones de catálogo».')
                ->schema([
                    Placeholder::make('exclusiones')
                        ->hiddenLabel()
                        ->content(fn (?Centro $record) => self::exclusiones($record)),
                ])
                ->visible(fn (?Centro $record) => $record?->tipoCentro?->permite_restriccion_en_centro ?? false),

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
                    Placeholder::make('config_uo_funcional')
                        ->label('UO funcional')
                        ->content(fn (?Centro $record) => $record === null ? '—' : $config()->uoFuncional($record)->nombre),

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

            // La baja va por su acción (CentroService::darDeBaja), no por el formulario
            Section::make('Vigencia')
                ->columns(2)
                ->schema([
                    DatePicker::make('fecha_alta')
                        ->label('Fecha de alta')
                        ->required()
                        ->default(now())
                        ->disabledOn('edit')
                        ->dehydrated(fn (string $operation) => $operation === 'create'),

                    Placeholder::make('estado')
                        ->label('Estado')
                        ->content(fn (?Centro $record) => $record === null ? '—'
                            : ($record->activo ? 'Activo' : 'De baja desde '.$record->fecha_baja?->format('d/m/Y')))
                        ->visibleOn('edit'),
                ]),

        ]);
    }

    /**
     * Si el usuario administra centros (alta, baja, renombrado y todos los campos).
     *
     * @return bool
     */
    public static function administra(): bool
    {
        return auth()->user()?->can('create', Centro::class) ?? false;
    }

    /**
     * Texto de la insignia de una propiedad sobrescribible (§4).
     *
     * @param string $origen 'tipo' o 'centro'.
     *
     * @return string
     */
    public static function insigniaOrigen(string $origen): string
    {
        return $origen === 'centro' ? 'Personalizado' : 'Heredado del tipo';
    }

    /**
     * Horario efectivo de hoy, con enlace al horario del centro para cambiarlo.
     *
     * @param Centro|null $record
     *
     * @return HtmlString|string
     */
    private static function horarioEfectivo(?Centro $record): HtmlString|string
    {
        if ($record === null) {
            return '—';
        }

        try {
            $horario = app(ConfiguracionEfectivaCentro::class)->horario($record, today());
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }

        $texto = $horario === null ? 'Sin horario' : sprintf(
            '%s · %s–%s (atención %s–%s)',
            collect($horario->diasLaborables)->map(fn (int $d) => mb_substr(TipoCentroResource::DIAS[$d], 0, 3))->implode(', '),
            $horario->apertura,
            $horario->cierre,
            $horario->inicioAtencion,
            $horario->finAtencion,
        );

        $propio = HorarioCentro::vigenteDelCentro($record->id);
        if ($propio === null) {
            return $texto;
        }

        return new HtmlString(e($texto).'<br><a href="'.e(HorarioCentroResource::getUrl('edit', ['record' => $propio])).'">Abrir el horario del centro</a>');
    }

    /**
     * Exclusiones del catálogo vigentes en el centro.
     *
     * @param Centro|null $record
     *
     * @return HtmlString|string
     */
    private static function exclusiones(?Centro $record): HtmlString|string
    {
        if ($record === null) {
            return '—';
        }

        $nombres = self::opcionesCatalogo($record);

        return CentroTipoAcciones::lineas(ExclusionCatalogo::where('centro_id', $record->id)->get()
            ->map(fn (ExclusionCatalogo $e) => $e->elemento_tipo->label().': '.($nombres[$e->elemento_tipo->value][$e->elemento_id] ?? "#{$e->elemento_id}")));
    }

    /**
     * Catálogo del tipo del centro por clase de elemento (id => nombre), sin
     * aplicar exclusiones: lo que el centro puede excluir.
     *
     * @param Centro $centro
     *
     * @return array<string, array<int, string>>
     */
    public static function opcionesCatalogo(Centro $centro): array
    {
        $tipo = $centro->tipoCentro;
        $fichas = $tipo->tiposFicha()->pluck('tipo_fichas.nombre', 'tipo_fichas.id');

        return [
            ElementoCatalogo::Prestacion->value => $tipo->prestaciones()->orderBy('prestaciones.nombre')->pluck('prestaciones.nombre', 'prestaciones.id')->all(),
            ElementoCatalogo::TipoPlan->value => $tipo->tiposPlan()->orderBy('tipos_plan.nombre')->pluck('tipos_plan.nombre', 'tipos_plan.id')->all(),
            // Sin fichas en el tipo, están todas: el centro puede excluir cualquiera
            ElementoCatalogo::TipoFicha->value => ($fichas->isNotEmpty() ? $fichas : TipoFicha::where('activo', true)->orderBy('nombre')->pluck('nombre', 'id'))->all(),
        ];
    }

    /**
     * Listado de centros.
     *
     * @param Table $table
     *
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
     * Dar de alta centros: solo el administrador (CentroPolicy::create).
     *
     * @return bool
     */
    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', Centro::class) ?? false;
    }

    /**
     * Editar: el administrador o el director del centro (CentroPolicy::update);
     * CentroService limita lo que cambia el director.
     *
     * @param Model $record
     *
     * @return bool
     */
    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    /**
     * Los centros no se borran: se dan de baja (CentroPolicy::delete).
     *
     * @param Model $record
     *
     * @return bool
     */
    public static function canDelete(Model $record): bool
    {
        return false;
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
     * @return array<string, PageRegistration>
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
