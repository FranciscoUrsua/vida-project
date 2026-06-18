<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\HorarioCentroResource\Pages;
use App\Filament\Resources\HorarioCentroResource\RelationManagers\TiposSlotsRelationManager;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Agenda\Enums\ModoAgenda;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Centro\Models\Centro;

/**
 * Recurso Filament para la gestión de horarios de centro.
 */
class HorarioCentroResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = HorarioCentro::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Horarios de centro';

    protected static string|\UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?string $modelLabel = 'Horario de centro';

    protected static ?string $pluralModelLabel = 'Horarios de centro';

    protected static ?int $navigationSort = 2;

    /**
     * Define el formulario de horarios de centro.
     *
     * @param Schema $schema Esquema base del formulario.
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
                        ->options(fn () => Centro::where('activo', true)->orderBy('nombre')->pluck('nombre', 'id'))
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
                        ->columnSpanFull(),

                    TimePicker::make('hora_apertura')
                        ->label('Hora de apertura')
                        ->required()
                        ->seconds(false),

                    TimePicker::make('hora_cierre')
                        ->label('Hora de cierre')
                        ->required()
                        ->seconds(false),

                    TimePicker::make('hora_inicio_atencion')
                        ->label('Inicio de atención al público')
                        ->required()
                        ->seconds(false),

                    TimePicker::make('hora_fin_atencion')
                        ->label('Fin de atención al público')
                        ->required()
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
                    ->getStateUsing(fn (HorarioCentro $r) => "{$r->hora_inicio_atencion} – {$r->hora_fin_atencion}"),

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
     * Declara los relation managers del recurso.
     *
     * @return array
     */
    public static function getRelationManagers(): array
    {
        return [
            TiposSlotsRelationManager::class,
        ];
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
