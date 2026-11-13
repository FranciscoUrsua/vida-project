<?php

namespace App\Filament\Resources;

use App\Filament\Acciones\CentroTipoAcciones;
use App\Filament\Resources\TipoCentroResource\Pages;
use App\Models\UnidadOrganizativa;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Modules\Centro\Enums\ModoAsignacionReferenciaCentro;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\HorarioTipoCentro;
use Modules\Centro\Models\TipoCentro;
use Modules\Prestaciones\Models\Prestacion;

/**
 * Recurso Filament de los tipos de centro (docs/modulo-tipo-centro.md §2 y §9):
 * denominación con vigencia, UO funcional, público, catálogo, horario y cierres
 * comunes, reglas de asignación y protocolo base. Solo el administrador del
 * sistema; el director ve lo que hereda su centro desde CentroResource.
 *
 * El código no cambia tras el alta, la denominación se programa con fecha y los
 * tipos no se borran: se dan de baja sin centros activos (RN-02, RN-11). Las
 * escrituras pasan por TipoCentroService (policy en el servidor).
 */
class TipoCentroResource extends Resource
{
    protected static ?string $model = TipoCentro::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationLabel = 'Tipos de centro';

    protected static string|\UnitEnum|null $navigationGroup = 'Centros y Servicios';

    protected static ?string $modelLabel = 'Tipo de centro';

    protected static ?string $pluralModelLabel = 'Tipos de centro';

    protected static ?int $navigationSort = 1;

    /** Días de la semana ISO para los horarios. */
    public const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    /**
     * Formulario de alta y edición de un tipo de centro.
     *
     * @param Schema $schema
     *
     * @return Schema
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identificación')
                ->columns(3)
                ->schema([
                    // Inmutable tras el alta (RN-02): en edición ni se envía
                    TextInput::make('codigo')
                        ->label('Código')
                        ->required()
                        ->maxLength(50)
                        ->alphaDash()
                        ->unique(ignoreRecord: true)
                        ->disabledOn('edit')
                        ->dehydrated(fn (string $operation) => $operation === 'create')
                        ->helperText('Clave interna. No se puede cambiar después.'),

                    // La denominación se fija al alta; después se programa con fecha (§8.1)
                    TextInput::make('nombre')
                        ->label('Denominación')
                        ->required()
                        ->maxLength(200)
                        ->visibleOn('create'),
                    TextInput::make('siglas')
                        ->label('Siglas')
                        ->required()
                        ->maxLength(50)
                        ->visibleOn('create'),

                    Placeholder::make('denominacion_vigente')
                        ->label('Denominación vigente')
                        ->content(fn (?TipoCentro $record) => $record === null ? '—' : "{$record->nombre} ({$record->siglas})")
                        ->visibleOn('edit'),
                    Placeholder::make('historial_denominaciones')
                        ->label('Historial de denominaciones')
                        ->content(fn (?TipoCentro $record) => CentroTipoAcciones::historialDenominaciones($record))
                        ->helperText('Para cambiarla, «Programar nueva denominación».')
                        ->columnSpan(2)
                        ->visibleOn('edit'),
                    Placeholder::make('estado')
                        ->label('Estado')
                        ->content(fn (?TipoCentro $record) => $record === null ? '—'
                            : ($record->activo ? 'Activo desde '.$record->fecha_alta->format('d/m/Y') : 'De baja desde '.$record->fecha_baja?->format('d/m/Y')))
                        ->visibleOn('edit'),
                ]),

            Section::make('Dependencia y público')
                ->columns(2)
                ->schema([
                    Select::make('unidad_organizativa_id')
                        ->label('UO funcional')
                        ->options(fn () => UnidadOrganizativa::activas()->orderBy('nombre')->pluck('nombre', 'id'))
                        ->searchable()
                        ->required()
                        ->helperText('UO que dirige el servicio. Sus usuarios y los de sus UO superiores tienen todos los centros del tipo en su ámbito.'),

                    CheckboxList::make('segmentos')
                        ->label('Público destinatario')
                        ->relationship('segmentos', 'nombre')
                        ->columns(2)
                        ->required(),
                ]),

            Section::make('Catálogo')
                ->columns(2)
                ->schema([
                    Placeholder::make('prestaciones')
                        ->label('Prestaciones')
                        ->content(fn (?TipoCentro $record) => self::prestacionesConEnlace($record))
                        ->helperText('Se asignan desde cada prestación (tipos de centro que la ofrecen).')
                        ->columnSpanFull()
                        ->visibleOn('edit'),

                    CheckboxList::make('tiposPlan')
                        ->label('Tipos de plan de intervención')
                        ->relationship('tiposPlan', 'nombre')
                        ->columns(2)
                        ->required(),

                    CheckboxList::make('tiposFicha')
                        ->label('Fichas de valoración')
                        ->relationship('tiposFicha', 'nombre')
                        ->columns(2)
                        ->helperText('Sin ninguna marcada, están disponibles todas.'),

                    Toggle::make('permite_restriccion_en_centro')
                        ->label('Permite restricción en centro')
                        ->helperText('Si se activa, cada centro del tipo puede excluir elementos de este catálogo (RN-08).')
                        ->columnSpanFull(),
                ]),

            Section::make('Horario y calendario')
                ->columns(2)
                ->schema([
                    Placeholder::make('horarios')
                        ->label('Horarios')
                        ->content(fn (?TipoCentro $record) => self::horarios($record))
                        ->helperText('Los centros que heredan el horario aplican el vigente. Para cambiarlo, «Programar horario».'),
                    Placeholder::make('cierres')
                        ->label('Cierres comunes')
                        ->content(fn (?TipoCentro $record) => CentroTipoAcciones::cierres($record))
                        ->helperText('Se suman a los cierres propios de cada centro. Para añadir uno, «Añadir cierre».'),
                ])
                ->visibleOn('edit'),

            Section::make('Horario inicial')
                ->description('Horario por defecto de los centros del tipo. Después se cambia programando uno nuevo con fecha.')
                ->columns(2)
                ->schema(self::camposHorario('horario.'))
                ->visibleOn('create'),

            Section::make('Asignación')
                ->columns(2)
                ->schema([
                    Toggle::make('inscripcion_libre')
                        ->label('Libre elección de centro')
                        ->helperText('Desactivado: la persona se adscribe al centro de su domicilio.'),

                    Select::make('modo_asignacion_referencia')
                        ->label('Profesional de referencia')
                        ->options(collect(ModoAsignacionReferenciaCentro::cases())->mapWithKeys(fn (ModoAsignacionReferenciaCentro $m) => [$m->value => $m->label()]))
                        ->default(ModoAsignacionReferenciaCentro::Sorteo->value)
                        ->required(),

                    TextInput::make('ventana_reparto_meses')
                        ->label('Ventana de reparto (meses)')
                        ->numeric()
                        ->minValue(1)
                        ->default(12)
                        ->required(),

                    TextInput::make('meses_inactividad_caso')
                        ->label('Meses sin apuntes para caso dormido')
                        ->numeric()
                        ->minValue(1)
                        ->default(6)
                        ->required(),
                ]),

            Section::make('Protocolo')
                ->schema([
                    Textarea::make('protocolo')
                        ->label('Protocolo base')
                        ->rows(5)
                        ->nullable()
                        ->helperText('Quién hace qué en los centros del tipo. Cada centro puede tener uno propio que lo sustituye.'),
                ]),

            Section::make('Centros del tipo')
                ->schema([
                    Placeholder::make('centros')
                        ->hiddenLabel()
                        ->content(fn (?TipoCentro $record) => $record === null ? '—' : CentroTipoAcciones::lineas(
                            $record->centros()->orderBy('nombre')->get()->map(fn (Centro $c) => $c->nombre.' · '.($c->activo ? 'activo' : 'de baja'))
                        )),
                ])
                ->visibleOn('edit'),
        ]);
    }

    /**
     * Campos de un horario semanal, con un prefijo de estado (`horario.` en el alta).
     *
     * @param string $prefijo
     *
     * @return list<Field>
     */
    public static function camposHorario(string $prefijo = ''): array
    {
        return [
            CheckboxList::make($prefijo.'dias_laborables')
                ->label('Días laborables')
                ->options(self::DIAS)
                ->default([1, 2, 3, 4, 5])
                ->columns(4)
                ->required()
                ->columnSpanFull(),
            TimePicker::make($prefijo.'hora_apertura')->label('Apertura')->seconds(false)->required(),
            TimePicker::make($prefijo.'hora_cierre')->label('Cierre')->seconds(false)->required()->after($prefijo.'hora_apertura'),
            TimePicker::make($prefijo.'hora_inicio_atencion')->label('Inicio de atención')->seconds(false)->required(),
            TimePicker::make($prefijo.'hora_fin_atencion')->label('Fin de atención')->seconds(false)->required()->after($prefijo.'hora_inicio_atencion'),
        ];
    }

    /**
     * Prestaciones del tipo, cada una enlazada a su ficha en PrestacionResource.
     *
     * @param TipoCentro|null $record
     *
     * @return HtmlString|string
     */
    private static function prestacionesConEnlace(?TipoCentro $record): HtmlString|string
    {
        if ($record === null) {
            return '—';
        }

        $enlaces = $record->prestaciones()->orderBy('prestaciones.nombre')->get()
            ->map(fn (Prestacion $p) => '<a href="'.e(PrestacionResource::getUrl('edit', ['record' => $p])).'">'.e($p->nombre).'</a>');

        return $enlaces->isEmpty() ? '—' : new HtmlString($enlaces->implode('<br>'));
    }

    /**
     * Horarios del tipo con su vigencia, del más reciente al más antiguo.
     *
     * @param TipoCentro|null $record
     *
     * @return HtmlString|string
     */
    private static function horarios(?TipoCentro $record): HtmlString|string
    {
        if ($record === null) {
            return '—';
        }

        return CentroTipoAcciones::lineas(
            HorarioTipoCentro::where('tipo_centro_id', $record->id)->orderByDesc('vigente_desde')->get()
                ->map(fn (HorarioTipoCentro $h) => sprintf(
                    '%s · %s–%s (atención %s–%s) · %s',
                    collect($h->dias_laborables)->map(fn ($d) => mb_substr(self::DIAS[(int) $d], 0, 3))->implode(', '),
                    substr($h->hora_apertura, 0, 5),
                    substr($h->hora_cierre, 0, 5),
                    substr($h->hora_inicio_atencion, 0, 5),
                    substr($h->hora_fin_atencion, 0, 5),
                    CentroTipoAcciones::vigencia($h->vigente_desde, $h->vigente_hasta),
                ))
        );
    }

    /**
     * Listado de tipos de centro.
     *
     * @param Table $table
     *
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('siglas')->label('Siglas')->sortable(),
                Tables\Columns\TextColumn::make('nombre')->label('Denominación')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('codigo')->label('Código')->fontFamily('mono')->toggleable(),
                Tables\Columns\TextColumn::make('unidadOrganizativa.nombre')->label('UO funcional')->toggleable(),
                Tables\Columns\TextColumn::make('centros_count')->label('Centros')->counts('centros')->alignCenter(),
                Tables\Columns\IconColumn::make('activo')->label('Activo')->boolean()->alignCenter(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')->label('Estado'),
            ])
            ->actions([
                // Sin borrado: los tipos se dan de baja (RN-11)
                EditAction::make(),
            ])
            ->defaultSort('nombre');
    }

    /**
     * Solo el administrador del sistema gestiona los tipos (TipoCentroPolicy::create).
     *
     * @return bool
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('create', TipoCentro::class) ?? false;
    }

    /**
     * Crear tipos: administrador.
     *
     * @return bool
     */
    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', TipoCentro::class) ?? false;
    }

    /**
     * Editar un tipo: administrador (TipoCentroPolicy::update).
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
     * Los tipos no se borran: se dan de baja (RN-11).
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
     * Tampoco el borrado masivo.
     *
     * @return bool
     */
    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * Páginas del recurso.
     *
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTiposCentro::route('/'),
            'create' => Pages\CreateTipoCentro::route('/create'),
            'edit' => Pages\EditTipoCentro::route('/{record}/edit'),
        ];
    }
}
