<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\TipoEscalaResource\Pages;
use App\Models\CatalogoSistema;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Escalas\Models\TipoEscala;

class TipoEscalaResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = TipoEscala::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Tipos de escala';
    protected static string|\UnitEnum|null $navigationGroup = 'Catálogos';
    protected static ?string $modelLabel = 'Tipo de escala';
    protected static ?string $pluralModelLabel = 'Tipos de escala';
    protected static ?int $navigationSort = 90;

    // -------------------------------------------------------------------------
    // Listado
    // -------------------------------------------------------------------------

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('fuente')
                    ->label('Fuente')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->fuente),

                TextColumn::make('contextos')
                    ->label('Contextos')
                    ->badge()
                    ->separator(','),

                ToggleColumn::make('activa')
                    ->label('Activa'),
            ])
            ->filters([
                TernaryFilter::make('activa')
                    ->label('Estado'),

                SelectFilter::make('contextos')
                    ->label('Contexto')
                    ->options(fn () => CatalogoSistema::opcionesParaSelect('escala.contexto'))
                    ->query(fn ($query, $data) => $data['value']
                        ? $query->whereJsonContains('contextos', $data['value'])
                        : $query
                    ),
            ])
            ->actions([
                EditAction::make(),
                Tables\Actions\Action::make('desactivar')
                    ->label('Desactivar')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->visible(fn (TipoEscala $record) => $record->activa)
                    ->action(fn (TipoEscala $record) => $record->update(['activa' => false])),
            ]);
    }

    // -------------------------------------------------------------------------
    // Formulario
    // -------------------------------------------------------------------------

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('pestanas')
                ->columnSpanFull()
                ->tabs([
                    Tab::make('Datos generales')
                        ->schema([
                            Section::make('Identificación')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('nombre')
                                        ->label('Nombre')
                                        ->required()
                                        ->maxLength(200)
                                        ->columnSpanFull(),

                                    TextInput::make('codigo')
                                        ->label('Código')
                                        ->required()
                                        ->unique(TipoEscala::class, ignoreRecord: true)
                                        ->maxLength(50)
                                        ->helperText('Identificador estable. No se puede modificar una vez que existen pases asociados.')
                                        ->disabled(fn (?TipoEscala $record) => $record?->pases()->exists() ?? false),

                                    TextInput::make('fuente')
                                        ->label('Fuente bibliográfica')
                                        ->maxLength(200),

                                    Toggle::make('activa')
                                        ->label('Activa'),

                                    Toggle::make('confirmar_instrucciones')
                                        ->label('Requiere confirmación de lectura antes del pase'),

                                    \Filament\Forms\Components\CheckboxList::make('contextos')
                                        ->label('Contextos de aplicación')
                                        ->options(fn () => CatalogoSistema::opcionesParaSelect('escala.contexto'))
                                        ->columnSpanFull(),
                                ]),

                            Section::make('Descripción e instrucciones')
                                ->schema([
                                    Textarea::make('descripcion')
                                        ->label('Descripción')
                                        ->rows(3),

                                    Textarea::make('instrucciones_aplicacion')
                                        ->label('Instrucciones de aplicación')
                                        ->helperText('Se muestran al profesional antes de comenzar el pase.')
                                        ->rows(6),
                                ]),
                        ]),

                    Tab::make('Estructura')
                        ->schema([
                            Repeater::make('secciones_escala')
                                ->label('Secciones')
                                ->reorderable()
                                ->reorderableWithDragAndDrop()
                                ->addActionLabel('Añadir sección')
                                ->schema([
                                    TextInput::make('titulo')
                                        ->label('Título de sección')
                                        ->required(),

                                    TextInput::make('id')
                                        ->label('ID de sección')
                                        ->required()
                                        ->helperText('Identificador único. No modificar una vez que existan pases.'),

                                    TextInput::make('orden')
                                        ->label('Orden')
                                        ->numeric()
                                        ->required(),

                                    Textarea::make('instrucciones')
                                        ->label('Instrucciones de sección')
                                        ->helperText('Se muestran al profesional al entrar en esta sección.')
                                        ->rows(2),

                                    Repeater::make('items')
                                        ->label('Ítems')
                                        ->reorderable()
                                        ->reorderableWithDragAndDrop()
                                        ->addActionLabel('Añadir ítem')
                                        ->schema([
                                            TextInput::make('id')
                                                ->label('ID de ítem')
                                                ->required()
                                                ->helperText('Identificador único. No modificar si ya existen pases.'),

                                            TextInput::make('texto')
                                                ->label('Texto del ítem')
                                                ->required(),

                                            TextInput::make('orden')
                                                ->label('Orden')
                                                ->numeric()
                                                ->required(),

                                            Textarea::make('instrucciones')
                                                ->label('Instrucciones del ítem')
                                                ->rows(2),

                                            Repeater::make('opciones')
                                                ->label('Opciones')
                                                ->addActionLabel('Añadir opción')
                                                ->minItems(2)
                                                ->schema([
                                                    TextInput::make('valor')
                                                        ->label('Valor')
                                                        ->numeric()
                                                        ->integer()
                                                        ->required(),

                                                    TextInput::make('etiqueta')
                                                        ->label('Etiqueta')
                                                        ->required(),
                                                ])
                                                ->columns(2),
                                        ]),
                                ]),
                        ]),

                    Tab::make('Rangos e interpretación')
                        ->schema([
                            Repeater::make('rangos')
                                ->label('Rangos de interpretación')
                                ->addActionLabel('Añadir rango')
                                ->schema([
                                    TextInput::make('desde')
                                        ->label('Desde')
                                        ->numeric()
                                        ->integer()
                                        ->required(),

                                    TextInput::make('hasta')
                                        ->label('Hasta')
                                        ->numeric()
                                        ->integer()
                                        ->required(),

                                    TextInput::make('etiqueta')
                                        ->label('Etiqueta')
                                        ->required(),

                                    TextInput::make('codigo')
                                        ->label('Código')
                                        ->required(),
                                ])
                                ->columns(4),

                            Textarea::make('nota_interpretacion')
                                ->label('Nota de interpretación')
                                ->helperText('Se muestra junto al resultado al cerrar el pase.')
                                ->rows(3),
                        ]),
                ]),
        ]);
    }

    // -------------------------------------------------------------------------
    // Páginas
    // -------------------------------------------------------------------------

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTipoEscalas::route('/'),
            'create' => Pages\CreateTipoEscala::route('/create'),
            'edit'   => Pages\EditTipoEscala::route('/{record}/edit'),
        ];
    }
}
