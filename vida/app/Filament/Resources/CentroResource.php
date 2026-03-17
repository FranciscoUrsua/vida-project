<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CentroResource\Pages;
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
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\Prestacion;
use Modules\Centro\Models\SegmentoPoblacion;
use Modules\Organizacion\Models\Distrito;

class CentroResource extends Resource
{
    protected static ?string $model = Centro::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Centros';
    protected static string|\UnitEnum|null $navigationGroup = 'Centros';
    protected static ?string $modelLabel = 'Centro';
    protected static ?string $pluralModelLabel = 'Centros';
    protected static ?int $navigationSort = 1;

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
                            'municipal_directo'    => 'Municipal directo',
                            'municipal_concertado' => 'Municipal concertado',
                            'privado_concertado'   => 'Privado concertado',
                            'privado_puro'         => 'Privado puro',
                        ])
                        ->required()
                        ->default('municipal_directo'),

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

                    Select::make('distrito_id')
                        ->label('Distrito')
                        ->options(fn () => Distrito::where('activo', true)->orderBy('nombre')->pluck('nombre', 'id'))
                        ->searchable()
                        ->nullable()
                        ->placeholder('Sin distrito asignado'),

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

            Section::make('Prestaciones')
                ->schema([
                    CheckboxList::make('prestaciones')
                        ->label('Prestaciones que ofrece')
                        ->relationship('prestaciones', 'nombre')
                        ->options(fn () => Prestacion::where('activo', true)->orderBy('nombre')->pluck('nombre', 'id'))
                        ->columns(2),
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
                    ->sortable('nombre'),

                Tables\Columns\TextColumn::make('tipo_gestion')
                    ->label('Tipo de gestión')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'municipal_directo'    => 'primary',
                        'municipal_concertado' => 'info',
                        'privado_concertado'   => 'warning',
                        'privado_puro'         => 'gray',
                        default                => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'municipal_directo'    => 'Municipal directo',
                        'municipal_concertado' => 'Municipal concertado',
                        'privado_concertado'   => 'Privado concertado',
                        'privado_puro'         => 'Privado puro',
                        default                => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('distrito.nombre')
                    ->label('Distrito')
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
                        'municipal_directo'    => 'Municipal directo',
                        'municipal_concertado' => 'Municipal concertado',
                        'privado_concertado'   => 'Privado concertado',
                        'privado_puro'         => 'Privado puro',
                    ]),

                Tables\Filters\TernaryFilter::make('activo')->label('Estado'),

                Tables\Filters\SelectFilter::make('distrito_id')
                    ->label('Distrito')
                    ->relationship('distrito', 'nombre'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('nombre');
    }

    public static function getRelationManagers(): array
    {
        return [
            ColeccionesPlazasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCentros::route('/'),
            'create' => Pages\CreateCentro::route('/create'),
            'edit'   => Pages\EditCentro::route('/{record}/edit'),
        ];
    }
}
