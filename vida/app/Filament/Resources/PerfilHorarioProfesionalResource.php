<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestionUsuarios;
use App\Filament\Resources\PerfilHorarioProfesionalResource\Pages;
use App\Support\Ambito\AmbitoGestionUsuarios;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Centro\Models\Centro;

/**
 * Recurso Filament para la gestión de perfiles horarios profesionales.
 */
class PerfilHorarioProfesionalResource extends Resource
{
    use AutorizaGestionUsuarios;

    protected static ?string $model = PerfilHorarioProfesional::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'Perfiles horarios';

    protected static string|\UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?string $modelLabel = 'Perfil horario';

    protected static ?string $pluralModelLabel = 'Perfiles horarios';

    protected static ?int $navigationSort = 3;

    /**
     * Define el formulario de perfiles horarios de profesional.
     *
     * @param Schema $schema Esquema base del formulario.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Profesional y centro')
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

            Section::make('Jornada y horario')
                ->columns(2)
                ->schema([
                    TextInput::make('jornada_semanal_horas')
                        ->label('Jornada semanal (horas)')
                        ->numeric()
                        ->required()
                        ->step(0.5)
                        ->helperText('Ej: 35, 17.5'),

                    Textarea::make('horario_habitual')
                        ->label('Horario habitual (JSON)')
                        ->rows(5)
                        ->nullable()
                        ->columnSpanFull()
                        ->helperText(
                            'Formato JSON por día de semana (1=Lunes ... 7=Domingo). '.
                            'Ejemplo: {"1": [{"inicio": "09:00", "fin": "14:00"}, {"inicio": "15:00", "fin": "17:00"}], "2": [...]}'
                        ),
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
                ->columns(2)
                ->schema([
                    Toggle::make('activo')
                        ->label('Activo')
                        ->default(true),

                    Textarea::make('notas')
                        ->label('Notas')
                        ->rows(2)
                        ->nullable()
                        ->columnSpanFull()
                        ->helperText('Ej: Reducción por conciliación familiar.'),
                ]),
        ]);
    }

    /**
     * Configura el listado de perfiles horarios de profesional.
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

                Tables\Columns\TextColumn::make('jornada_semanal_horas')
                    ->label('Jornada (h)')
                    ->formatStateUsing(fn ($state) => "{$state}h")
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('vigente_desde')
                    ->label('Vigente desde')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('vigente_hasta')
                    ->label('Vigente hasta')
                    ->date('d/m/Y')
                    ->placeholder('Indefinido'),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('pendiente_verificar')
                    ->label('Horario')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'No personalizado' : 'Verificado')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray'),
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
            ->defaultSort('usuario_id');
    }

    /**
     * Declara las páginas del recurso de perfiles horarios.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPerfilesHorarioProfesional::route('/'),
            'create' => Pages\CreatePerfilHorarioProfesional::route('/create'),
            'edit' => Pages\EditPerfilHorarioProfesional::route('/{record}/edit'),
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
