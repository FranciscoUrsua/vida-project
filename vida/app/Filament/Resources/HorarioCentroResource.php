<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\HorarioCentroResource\Pages;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Modules\Agenda\Enums\ModoAgenda;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;

/**
 * Recurso Filament para la gestión de horarios de centro.
 */
class HorarioCentroResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = HorarioCentro::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Horarios de centro';

    protected static string|\UnitEnum|null $navigationGroup = 'Agenda — Configuración';

    protected static ?string $modelLabel = 'Horario de centro';

    protected static ?string $pluralModelLabel = 'Horarios de centro';

    protected static ?int $navigationSort = 2;

    /**
     * Define el formulario de horarios de centro.
     *
     * @param Schema $schema Esquema base del formulario.
     *
     * @return Schema
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Centro y nombre')
                ->columns(2)
                ->schema([
                    Select::make('centro_id')
                        ->label('Centro')
                        // El director solo elige su centro (§9 de docs/modulo-tipo-centro.md)
                        ->options(fn () => static::centrosGestionables()->pluck('nombre', 'id'))
                        ->searchable()
                        ->required(),

                    TextInput::make('nombre')
                        ->label('Nombre del horario')
                        ->required()
                        ->maxLength(200)
                        ->placeholder('Ej: Horario general, Horario verano 2025'),
                ]),

            Section::make('Modo de agenda')
                ->schema([
                    Select::make('modo_agenda')
                        ->label('Modo de agenda')
                        ->options(collect(ModoAgenda::cases())->mapWithKeys(
                            fn (ModoAgenda $m) => [$m->value => $m->label()]
                        ))
                        ->required()
                        ->default(ModoAgenda::Estandar->value)
                        ->helperText(
                            'Básico: cuadrante automático sin supervisión, sin tipos de slot configurables. '.
                            'Estándar: supervisor genera y publica cuadrante, tipos de slot y urgencias. '.
                            'Avanzado: todo lo anterior más propuesta IA y profesionales itinerantes.'
                        ),
                ]),

            Section::make('Días y horario')
                ->columns(2)
                ->schema([
                    // Con herencia, el horario semanal es el del tipo y los campos quedan vacíos (docs/modulo-tipo-centro.md §5.1)
                    Toggle::make('hereda_horario_tipo')
                        ->label('Heredar horario del tipo')
                        ->default(true)
                        ->live()
                        ->columnSpanFull(),

                    Placeholder::make('horario_del_tipo')
                        ->label('Horario del tipo')
                        ->visible(fn (Get $get) => (bool) $get('hereda_horario_tipo'))
                        ->content(function (Get $get): string {
                            $centro = Centro::find($get('centro_id'));
                            $horario = $centro === null ? null : app(ConfiguracionEfectivaCentro::class)->horario($centro, today());

                            return $horario === null || $horario->origen !== 'tipo'
                                ? '—'
                                : sprintf('Días %s · %s–%s (atención %s–%s)', implode(', ', $horario->diasLaborables), $horario->apertura, $horario->cierre, $horario->inicioAtencion, $horario->finAtencion);
                        })
                        ->columnSpanFull(),

                    CheckboxList::make('dias_laborables')
                        ->label('Días laborables')
                        ->options([
                            1 => 'Lunes',
                            2 => 'Martes',
                            3 => 'Miércoles',
                            4 => 'Jueves',
                            5 => 'Viernes',
                            6 => 'Sábado',
                            7 => 'Domingo',
                        ])
                        ->columns(4)
                        ->hidden(fn (Get $get) => (bool) $get('hereda_horario_tipo'))
                        ->columnSpanFull(),

                    TimePicker::make('hora_apertura')
                        ->hidden(fn (Get $get) => (bool) $get('hereda_horario_tipo'))
                        ->label('Hora de apertura')
                        ->required(fn (Get $get) => ! $get('hereda_horario_tipo'))
                        ->seconds(false),

                    TimePicker::make('hora_cierre')
                        ->hidden(fn (Get $get) => (bool) $get('hereda_horario_tipo'))
                        ->label('Hora de cierre')
                        ->required(fn (Get $get) => ! $get('hereda_horario_tipo'))
                        ->seconds(false),

                    TimePicker::make('hora_inicio_atencion')
                        ->hidden(fn (Get $get) => (bool) $get('hereda_horario_tipo'))
                        ->label('Inicio de atención al público')
                        ->required(fn (Get $get) => ! $get('hereda_horario_tipo'))
                        ->seconds(false),

                    TimePicker::make('hora_fin_atencion')
                        ->hidden(fn (Get $get) => (bool) $get('hereda_horario_tipo'))
                        ->label('Fin de atención al público')
                        ->required(fn (Get $get) => ! $get('hereda_horario_tipo'))
                        ->seconds(false),

                    TextInput::make('buffer_inicio_minutos')
                        ->label('Buffer inicio (minutos)')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->helperText('Minutos sin citas al inicio del turno (revisión de pendientes, etc.)'),

                    TextInput::make('buffer_fin_minutos')
                        ->label('Buffer fin (minutos)')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->helperText('Minutos sin citas al final del turno.'),

                    TextInput::make('dias_ausencia_prolongada')
                        ->label('Umbral de ausencia prolongada (días)')
                        ->numeric()
                        ->integer()
                        ->default(15)
                        ->minValue(1)
                        ->required()
                        ->helperText('Una ausencia más larga saca al profesional del sorteo de referencias mientras dure.'),
                ]),

            Section::make('Citas')
                ->description('Plazos máximos por urgencia (días laborables) para calcular la fecha límite de una solicitud de cita. Vacío: 20, 7 y 2 días.')
                ->columns(4)
                ->schema([
                    TextInput::make('plazos_urgencia.ordinaria')->label('Ordinaria')->numeric()->integer()->minValue(1)->placeholder('20'),
                    TextInput::make('plazos_urgencia.preferente')->label('Preferente')->numeric()->integer()->minValue(1)->placeholder('7'),
                    TextInput::make('plazos_urgencia.urgente')->label('Urgente')->numeric()->integer()->minValue(1)->placeholder('2'),
                    TextInput::make('dias_aviso_cierre_supervisor')
                        ->label('Aviso de citas sin cerrar (días)')
                        ->numeric()->integer()->minValue(1)->default(3)->required()
                        ->helperText('Días laborables que una cita puede seguir sin apunte ni incomparecencia antes de avisar al supervisor.'),
                ]),

            Section::make('Tipos de slot')
                ->schema([
                    Select::make('tiposSlot')
                        ->label('Tipos de slot que ofrece el centro')
                        ->relationship('tiposSlot', 'nombre', fn ($query) => $query->where('activo', true))
                        ->multiple()
                        ->preload()
                        ->helperText('Del catálogo global de tipos de slot. Al publicar el cuadrante se generan slots de estos tipos; sin ninguno, no se genera ninguno.'),
                ]),

            Section::make('Vigencia')
                ->columns(2)
                ->schema([
                    DatePicker::make('vigente_desde')
                        ->label('Vigente desde')
                        ->required(),

                    DatePicker::make('vigente_hasta')
                        ->label('Vigente hasta')
                        ->nullable()
                        ->helperText('Dejar vacío para vigencia indefinida.'),
                ]),

            Section::make('Estado y notas')
                ->schema([
                    Toggle::make('activo')
                        ->label('Activo')
                        ->default(true),

                    Textarea::make('notas')
                        ->label('Notas')
                        ->rows(3)
                        ->nullable(),
                ]),
        ]);
    }

    /**
     * Configura el listado de horarios de centro.
     *
     * @param Table $table Tabla base.
     *
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('centro.nombre')
                    ->label('Centro')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('modo_agenda')
                    ->label('Modo')
                    ->badge()
                    ->formatStateUsing(fn (ModoAgenda $state) => $state->label())
                    ->color(fn (ModoAgenda $state) => match ($state) {
                        ModoAgenda::Basico => 'gray',
                        ModoAgenda::Estandar => 'info',
                        ModoAgenda::Avanzado => 'success',
                    }),

                Tables\Columns\TextColumn::make('horario')
                    ->label('Horario de atención')
                    ->getStateUsing(fn (HorarioCentro $r) => $r->hereda_horario_tipo ? 'Del tipo de centro' : "{$r->hora_inicio_atencion} – {$r->hora_fin_atencion}"),

                Tables\Columns\TextColumn::make('vigente_desde')
                    ->label('Vigente desde')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('vigente_hasta')
                    ->label('Vigente hasta')
                    ->date('d/m/Y')
                    ->placeholder('Indefinido'),

                Tables\Columns\ToggleColumn::make('activo')
                    ->label('Activo'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('centro_id')
                    ->label('Centro')
                    ->relationship('centro', 'nombre'),

                Tables\Filters\TernaryFilter::make('activo')->label('Estado'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('nombre');
    }

    /**
     * Ver el listado: los roles de gestión y los directores de centro (§9).
     *
     * @return bool
     */
    public static function canViewAny(): bool
    {
        $usuario = auth()->user();

        return $usuario !== null && (static::esGestion($usuario) || Centro::dirigidosPor($usuario)->exists());
    }

    /**
     * Crear un horario: quien pueda ver el listado (el centro se limita en el formulario y al guardar).
     *
     * @return bool
     */
    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    /**
     * Editar un horario: gestión, o el director de su centro (CentroPolicy::gestionarCalendario).
     *
     * @param Model $record
     *
     * @return bool
     */
    public static function canEdit(Model $record): bool
    {
        $usuario = auth()->user();

        return $usuario !== null && (static::esGestion($usuario) || ($record->centro !== null && $usuario->can('gestionarCalendario', $record->centro)));
    }

    /**
     * Borrar un horario: lo mismo que editarlo.
     *
     * @param Model $record
     *
     * @return bool
     */
    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    /**
     * Los directores solo ven los horarios de los centros que dirigen.
     *
     * @return Builder<HorarioCentro>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $usuario = auth()->user();

        if ($usuario === null || static::esGestion($usuario)) {
            return $query;
        }

        return $query->whereIn('centro_id', Centro::dirigidosPor($usuario)->select('id'));
    }

    /**
     * Centros activos cuyo horario puede gestionar el usuario autenticado.
     *
     * @return Collection<int, Centro>
     */
    public static function centrosGestionables(): Collection
    {
        $usuario = auth()->user();
        $query = Centro::where('activo', true)->orderBy('nombre');

        return $usuario !== null && static::esGestion($usuario)
            ? $query->get()
            : $query->dirigidosPor($usuario)->get();
    }

    /**
     * Roles de gestión con acceso a todos los horarios (los de AutorizaGestion).
     *
     * @param User $usuario
     *
     * @return bool
     */
    private static function esGestion(User $usuario): bool
    {
        return $usuario->hasAnyRole(['adm_sistema', 'adm_usuarios']);
    }

    /**
     * Declara los relation managers del recurso.
     *
     * @return array<int, class-string>
     */
    public static function getRelationManagers(): array
    {
        return [];
    }

    /**
     * Declara las páginas del recurso de horarios de centro.
     *
     * @return array
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHorariosCentro::route('/'),
            'create' => Pages\CreateHorarioCentro::route('/create'),
            'edit' => Pages\EditHorarioCentro::route('/{record}/edit'),
        ];
    }
}
