<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\CentroResource\Pages;
use App\Filament\Resources\CentroResource\RelationManagers\AmbitosTerritorialesRelationManager;
use App\Filament\Resources\CentroResource\RelationManagers\ColeccionesPlazasRelationManager;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
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
use Modules\Centro\Models\SegmentoPoblacion;

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
                    Toggle::make('inscripcion_libre')
                        ->label('Inscripción libre')
                        ->helperText('Si está activo, los ciudadanos pueden inscribirse sin derivación de un profesional.')
                        ->default(false),

                    KeyValue::make('horario')
                        ->label('Horario de atención')
                        ->keyLabel('Día / tramo')
                        ->valueLabel('Horario')
                        ->nullable()
                        ->helperText('Ej: "Lunes–Viernes" → "08:30–15:00"'),

                    Textarea::make('notas')
                        ->label('Notas internas')
                        ->rows(3)
                        ->nullable(),
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

            Section::make('Segmentos de población')
                ->schema([
                    CheckboxList::make('segmentosPoblacion')
                        ->label('Colectivos atendidos')
                        ->relationship('segmentosPoblacion', 'nombre')
                        ->options(fn () => SegmentoPoblacion::where('activo', true)->orderBy('nombre')->pluck('nombre', 'id'))
                        ->columns(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre_corto')
                    ->label('Nombre')
                    ->description(fn (Centro $record) => $record->nombre)
                    ->searchable(['nombre', 'nombre_corto'])
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('nombre', $direction)),

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
                EditAction::make(),
                DeleteAction::make()
                    ->authorize(fn (Model $record) => static::canDelete($record)),
            ])
            ->defaultSort('nombre');
    }

    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('centros.editar') ?? false;
    }

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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCentros::route('/'),
            'create' => Pages\CreateCentro::route('/create'),
            'edit' => Pages\EditCentro::route('/{record}/edit'),
        ];
    }
}
