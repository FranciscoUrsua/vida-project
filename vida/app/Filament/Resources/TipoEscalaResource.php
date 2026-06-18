<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\TipoEscalaResource\Pages;
use App\Models\CatalogoSistema;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Escalas\Models\TipoEscala;

/**
 * Recurso Filament para la gestión de tipos de escala.
 */
class TipoEscalaResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = TipoEscala::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Tipos de escala';

    protected static string|\UnitEnum|null $navigationGroup = 'Informes y Plantillas';

    protected static ?string $modelLabel = 'Tipo de escala';

    protected static ?string $pluralModelLabel = 'Tipos de escala';

    protected static ?int $navigationSort = 4;

    // -------------------------------------------------------------------------
    // Listado
    // -------------------------------------------------------------------------

    /**
     * Configura el listado de tipos de escala.
     *
     * @param Table $table Tabla base.
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->fontFamily('mono')
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
                Action::make('desactivar')
                    ->label('Desactivar')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->visible(fn (TipoEscala $record) => $record->activa)
                    ->action(fn (TipoEscala $record) => $record->update(['activa' => false])),
            ]);
    }

    // -------------------------------------------------------------------------
    /**
     * Define el formulario de tipos de escala.
     *
     * @param Schema $schema Esquema base del formulario.
     * @return Schema
     */
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

                                    CheckboxList::make('contextos')
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
                            Builder::make('schema')
                                ->label('Secciones e ítems')
                                ->blocks([
                                    Builder\Block::make('seccion')
                                        ->label(fn (?array $state): string => filled($state['titulo'] ?? null)
                                                ? $state['titulo']
                                                : 'Nueva sección'
                                        )
                                        ->icon('heroicon-o-list-bullet')
                                        ->schema([

                                            Placeholder::make('aviso_inmutabilidad')
                                                ->content('Este instrumento tiene pases registrados. Los ítems y opciones existentes no se pueden modificar para preservar la integridad del historial. Solo es posible añadir nuevas secciones o ítems.')
                                                ->visible(fn (?TipoEscala $record): bool => $record !== null && $record->pases()->exists()
                                                ),

                                            TextInput::make('titulo')
                                                ->label('Título de la sección')
                                                ->required()
                                                ->maxLength(200)
                                                ->live(onBlur: true),

                                            Textarea::make('instrucciones')
                                                ->label('Instrucciones de sección')
                                                ->hint('Se muestran al profesional al entrar en esta sección. Opcional.')
                                                ->rows(3)
                                                ->nullable(),

                                            Repeater::make('items')
                                                ->label('Ítems')
                                                ->addActionLabel('Añadir ítem')
                                                ->collapsible()
                                                ->cloneable()
                                                ->reorderableWithDragAndDrop()
                                                ->itemLabel(fn (array $state): ?string => $state['texto'] ?? null)
                                                ->schema([

                                                    TextInput::make('texto')
                                                        ->label('Texto del ítem')
                                                        ->required()
                                                        ->maxLength(500)
                                                        ->disabled(fn (?TipoEscala $record): bool => $record !== null && $record->pases()->exists()
                                                        )
                                                        ->live(onBlur: true),

                                                    Textarea::make('instrucciones')
                                                        ->label('Instrucciones del ítem')
                                                        ->hint('Criterio de puntuación visible durante el pase. Opcional.')
                                                        ->rows(2)
                                                        ->nullable(),

                                                    Repeater::make('opciones')
                                                        ->label('Opciones de respuesta')
                                                        ->addActionLabel('Añadir opción')
                                                        ->reorderableWithDragAndDrop(false)
                                                        ->columns(2)
                                                        ->schema([
                                                            TextInput::make('valor')
                                                                ->label('Valor numérico')
                                                                ->numeric()
                                                                ->integer()
                                                                ->required(),
                                                            TextInput::make('etiqueta')
                                                                ->label('Etiqueta')
                                                                ->required()
                                                                ->maxLength(100),
                                                        ])
                                                        ->minItems(2)
                                                        ->disabled(fn (?TipoEscala $record): bool => $record !== null && $record->pases()->exists()
                                                        ),
                                                ]),
                                        ]),
                                ])
                                ->collapsible()
                                ->collapsed()
                                ->cloneable()
                                ->reorderableWithDragAndDrop()
                                ->addActionLabel('Añadir sección')
                                ->hint('Arrastra para reordenar secciones. Haz clic en el título para expandir.')
                                ->afterStateHydrated(function (Builder $component, mixed $state): void {
                                    if (empty($state)) {
                                        $component->state([]);

                                        return;
                                    }

                                    // Si ya está en formato Builder (['type'=>..., 'data'=>...]), no transformar
                                    $first = is_array($state) ? reset($state) : null;
                                    if (is_array($first) && isset($first['type'])) {
                                        return;
                                    }

                                    // Viene del modelo: ['secciones' => [...]]
                                    if (is_array($state) && isset($state['secciones'])) {
                                        $builderState = collect($state['secciones'])
                                            ->filter(fn ($s) => is_array($s))
                                            ->map(fn (array $seccion) => [
                                                'type' => 'seccion',
                                                'data' => $seccion,
                                            ])
                                            ->values()
                                            ->all();

                                        $component->state($builderState);

                                        return;
                                    }

                                    // Fallback: estado inesperado — resetear en lugar de explotar
                                    $component->state([]);
                                })
                                ->dehydrateStateUsing(function (mixed $state): array {
                                    if (empty($state) || ! is_array($state)) {
                                        return ['secciones' => []];
                                    }

                                    $secciones = collect($state)
                                        ->filter(fn ($block) => is_array($block) && ($block['type'] ?? null) === 'seccion')
                                        ->map(function (array $block, int $si) {
                                            $seccion = $block['data'] ?? [];

                                            if (! is_array($seccion)) {
                                                return null;
                                            }

                                            $seccion['id'] ??= 'sec_'.($si + 1);
                                            $seccion['orden'] = $si + 1;

                                            $seccion['items'] = collect($seccion['items'] ?? [])
                                                ->filter(fn ($item) => is_array($item))
                                                ->map(function (array $item, int $ii) use ($si) {
                                                    $item['id'] ??= 'item_'.($si + 1).'_'.($ii + 1);
                                                    $item['orden'] = $ii + 1;

                                                    return $item;
                                                })
                                                ->values()
                                                ->all();

                                            return $seccion;
                                        })
                                        ->filter()
                                        ->values()
                                        ->all();

                                    return ['secciones' => $secciones];
                                }),
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

    /**
     * Declara las páginas del catálogo de tipos de escala.
     *
     * @return array
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTipoEscalas::route('/'),
            'create' => Pages\CreateTipoEscala::route('/create'),
            'edit' => Pages\EditTipoEscala::route('/{record}/edit'),
        ];
    }
}
