<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\PrestacionResource\Pages;
use App\Filament\Resources\PrestacionResource\RelationManagers\VersionesRelationManager;
use App\Models\CatalogoSistema;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Prestaciones\Models\Prestacion;

/**
 * Recurso Filament para la gestión de prestaciones.
 */
class PrestacionResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = Prestacion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Prestaciones';

    protected static string|\UnitEnum|null $navigationGroup = 'Centros y Servicios';

    protected static ?string $modelLabel = 'Prestación';

    protected static ?string $pluralModelLabel = 'Prestaciones';

    protected static ?int $navigationSort = 1;

    /**
     * Define el formulario de prestaciones.
     *
     * @param Schema $schema Esquema base del formulario.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Identificación')
                ->columns(2)
                ->schema([
                    TextInput::make('codigo')
                        ->label('Código')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(30)
                        ->helperText('Código jerárquico del catálogo (ej: 010101)'),

                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Select::make('tipo_prestacion')
                        ->label('Tipo de prestación')
                        ->options([
                            'servicio' => 'Servicio',
                            'economica' => 'Económica',
                        ])
                        ->required(),

                    Select::make('nivel_garantia')
                        ->label('Nivel de garantía')
                        ->options([
                            'garantizada' => 'Garantizada',
                            'condicionada' => 'Condicionada',
                        ])
                        ->required(),
                ]),

            Section::make('Clasificación')
                ->columns(2)
                ->schema([
                    Select::make('objetivo_general')
                        ->label('Objetivo general')
                        ->options(fn () => CatalogoSistema::opcionesParaSelect('prestacion.objetivo_general'))
                        ->live()
                        ->afterStateUpdated(fn ($set) => $set('categoria_especifica', null)),

                    Select::make('categoria_especifica')
                        ->label('Categoría específica')
                        ->options(fn (Get $get) => CatalogoSistema::opcionesParaSelectConPrefijo(
                            'prestacion.categoria',
                            $get('objetivo_general') ?? ''
                        ))
                        ->disabled(fn (Get $get) => blank($get('objetivo_general')))
                        ->helperText('Selecciona primero el objetivo general'),

                    Select::make('nivel_atencion')
                        ->label('Nivel de atención')
                        ->options(fn () => CatalogoSistema::opcionesParaSelect('prestacion.nivel_atencion')),

                    Select::make('competencia')
                        ->label('Competencia')
                        ->options(fn () => CatalogoSistema::opcionesParaSelect('prestacion.competencia')),

                    Select::make('forma_gestion')
                        ->label('Forma de gestión')
                        ->options(fn () => CatalogoSistema::opcionesParaSelect('prestacion.forma_gestion')),

                    Select::make('financiacion')
                        ->label('Financiación')
                        ->options(fn () => CatalogoSistema::opcionesParaSelect('prestacion.financiacion')),

                    TextInput::make('ambito_territorial')
                        ->label('Ámbito territorial')
                        ->maxLength(255),

                    CheckboxList::make('modalidades')
                        ->label('Modalidades de atención')
                        ->options([
                            'presencial' => 'Presencial',
                            'telefonica' => 'Telefónica',
                            'telematica' => 'Telemática',
                        ])
                        ->columns(3),

                    CheckboxList::make('poblacion_destinataria')
                        ->label('Población destinataria')
                        ->options(fn () => CatalogoSistema::opcionesParaSelect('prestacion.poblacion'))
                        ->columns(3)
                        ->columnSpanFull(),
                ]),

            Section::make('Tipos de centro')
                ->schema([
                    CheckboxList::make('tiposCentroKeys')
                        ->label('Tipos de centro que pueden ofrecer esta prestación')
                        ->options(fn () => CatalogoSistema::opcionesParaSelect('centro.tipo'))
                        ->columns(3)
                        ->dehydrated(false)
                        ->afterStateHydrated(function ($state, $record, $set) {
                            if ($record) {
                                $set('tiposCentroKeys', $record->tiposCentro->pluck('tipo_centro')->toArray());
                            }
                        }),
                ]),

            Section::make('Descripción')
                ->columns(1)
                ->schema([
                    Textarea::make('finalidad')
                        ->label('Finalidad')
                        ->rows(3),

                    Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(4),

                    Textarea::make('requisitos')
                        ->label('Requisitos de acceso')
                        ->rows(4),

                    Textarea::make('procedimiento')
                        ->label('Procedimiento')
                        ->rows(4),
                ]),

            Section::make('Condiciones y obligaciones')
                ->columns(2)
                ->schema([
                    Textarea::make('condiciones_acceso')
                        ->label('Condiciones de acceso y prioridad')
                        ->rows(3)
                        ->columnSpanFull(),

                    Textarea::make('obligaciones')
                        ->label('Obligaciones del beneficiario')
                        ->rows(3),

                    Textarea::make('compatibilidad')
                        ->label('Compatibilidad con otras prestaciones')
                        ->rows(3),

                    TextInput::make('aportacion_usuario')
                        ->label('Aportación del usuario')
                        ->maxLength(255)
                        ->helperText("'Gratuito', importe o descripción libre"),

                    TextInput::make('plazo_concesion')
                        ->label('Plazo de concesión')
                        ->maxLength(255),

                    TextInput::make('duracion_maxima')
                        ->label('Duración máxima')
                        ->maxLength(255),
                ]),

            Section::make('Gestión y normativa')
                ->columns(2)
                ->schema([
                    TextInput::make('proveedor')
                        ->label('Proveedor')
                        ->maxLength(255),

                    TextInput::make('financiacion_detalle')
                        ->label('Detalle de financiación')
                        ->maxLength(255),

                    Textarea::make('normativa')
                        ->label('Normativa de referencia')
                        ->rows(3)
                        ->columnSpanFull(),

                    Textarea::make('estandares_calidad')
                        ->label('Estándares de calidad')
                        ->rows(3)
                        ->columnSpanFull(),

                    TextInput::make('informacion_complementaria')
                        ->label('URL de información complementaria')
                        ->url()
                        ->maxLength(500)
                        ->columnSpanFull(),
                ]),

            Section::make('Estado')
                ->schema([
                    Toggle::make('activa')
                        ->label('Prestación activa')
                        ->default(true),
                ]),

        ]);
    }

    /**
     * Configura el listado de prestaciones.
     *
     * @param Table $table Tabla base.
     */
    public static function table(Table $table): Table
    {
        $objetivos = CatalogoSistema::opcionesParaSelect('prestacion.objetivo_general');

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->sortable()
                    ->searchable()
                    ->width('8rem'),

                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\BadgeColumn::make('tipo_prestacion')
                    ->label('Tipo')
                    ->colors([
                        'info' => 'servicio',
                        'warning' => 'economica',
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'servicio' => 'Servicio',
                        'economica' => 'Económica',
                        default => ucfirst($state),
                    }),

                Tables\Columns\BadgeColumn::make('nivel_garantia')
                    ->label('Garantía')
                    ->colors([
                        'success' => 'garantizada',
                        'gray' => 'condicionada',
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'garantizada' => 'Garantizada',
                        'condicionada' => 'Condicionada',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('objetivo_general')
                    ->label('Objetivo')
                    ->formatStateUsing(fn (?string $state) => $state ? ($objetivos[$state] ?? $state) : '—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('nivel_atencion')
                    ->label('Nivel')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'asp' => 'ASP',
                        'especializada' => 'Especializada',
                        'no_aplica' => 'No aplica',
                        default => $state ?? '—',
                    })
                    ->toggleable(),

                Tables\Columns\ToggleColumn::make('activa')
                    ->label('Activa'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('objetivo_general')
                    ->label('Objetivo general')
                    ->options(fn () => CatalogoSistema::opcionesParaSelect('prestacion.objetivo_general')),

                Tables\Filters\SelectFilter::make('tipo_prestacion')
                    ->label('Tipo de prestación')
                    ->options([
                        'servicio' => 'Servicio',
                        'economica' => 'Económica',
                    ]),

                Tables\Filters\SelectFilter::make('nivel_garantia')
                    ->label('Nivel de garantía')
                    ->options([
                        'garantizada' => 'Garantizada',
                        'condicionada' => 'Condicionada',
                    ]),

                Tables\Filters\SelectFilter::make('nivel_atencion')
                    ->label('Nivel de atención')
                    ->options(fn () => CatalogoSistema::opcionesParaSelect('prestacion.nivel_atencion')),

                Tables\Filters\TernaryFilter::make('activa')
                    ->label('Estado'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('codigo');
    }

    /**
     * Declara los relation managers del recurso de prestaciones.
     *
     * @return array<int, class-string>
     */
    public static function getRelationManagers(): array
    {
        return [
            VersionesRelationManager::class,
        ];
    }

    /**
     * Declara las páginas del recurso de prestaciones.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrestaciones::route('/'),
            'create' => Pages\CreatePrestacion::route('/create'),
            'edit' => Pages\EditPrestacion::route('/{record}/edit'),
        ];
    }
}
