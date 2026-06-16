<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\TipoFichaResource\Pages;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Modules\Intervencion\Models\TipoFicha;

/**
 * Recurso Filament para la gestión de fichas de valoración configurables.
 *
 * Cada TipoFicha define un formulario con campos tipados (texto, número, select,
 * booleano, fecha, escala) que el profesional rellena durante la valoración.
 * El schema JSON se edita mediante un Builder visual con bloques por tipo de campo.
 */
class TipoFichaResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = TipoFicha::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Fichas de Valoración';

    protected static string|\UnitEnum|null $navigationGroup = 'Informes y Plantillas';

    protected static ?string $modelLabel = 'Ficha de Valoración';

    protected static ?string $pluralModelLabel = 'Fichas de Valoración';

    protected static ?int $navigationSort = 3;

    // -------------------------------------------------------------------------
    // Listado
    // -------------------------------------------------------------------------

    /**
     * Configura la tabla de tipos de ficha.
     *
     * @param Table $table Tabla Filament a configurar.
     * @return Table Tabla configurada.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('descripcion')
                    ->label('Contexto y uso')
                    ->limit(80)
                    ->placeholder('—'),

                TextColumn::make('num_campos')
                    ->label('Campos')
                    ->getStateUsing(fn (TipoFicha $record): int => count($record->schema['campos'] ?? [])),

                ToggleColumn::make('activo')
                    ->label('Activa'),

                TextColumn::make('updated_at')
                    ->label('Última modificación')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('activo')
                    ->label('Estado')
                    ->trueLabel('Activas')
                    ->falseLabel('Inactivas')
                    ->placeholder('Todas'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (TipoFicha $record): bool => ! $record->tieneFichasAsociadas()),
            ])
            ->defaultSort('nombre');
    }

    // -------------------------------------------------------------------------
    // Formulario
    // -------------------------------------------------------------------------

    /**
     * Configura el formulario de tipos de ficha.
     *
     * @param Schema $schema Schema Filament a configurar.
     * @return Schema Schema configurado.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('pestanas')
                ->columnSpanFull()
                ->tabs([

                    Tab::make('Datos generales')
                        ->schema([
                            Section::make()
                                ->schema([
                                    TextInput::make('nombre')
                                        ->label('Nombre de la ficha')
                                        ->required()
                                        ->maxLength(200),

                                    Textarea::make('descripcion')
                                        ->label('Contexto y uso')
                                        ->helperText('Explica cuándo usar esta ficha y qué información recoge.')
                                        ->rows(4),

                                    Toggle::make('activo')
                                        ->label('Activa')
                                        ->default(true),
                                ]),
                        ]),

                    Tab::make('Campos de la ficha')
                        ->schema([
                            Placeholder::make('aviso_inmutabilidad')
                                ->content('Esta ficha tiene datos cumplimentados. Puedes añadir nuevos campos, pero no puedes eliminar ni cambiar el tipo de los existentes.')
                                ->visible(fn (?TipoFicha $record): bool => $record !== null && $record->tieneFichasAsociadas()),

                            Builder::make('schema')
                                ->label('Campos de la ficha')
                                ->blocks([
                                    Builder\Block::make('texto')
                                        ->label(fn (?array $state): string => 'Texto libre: '.($state['etiqueta'] ?? '—'))
                                        ->schema(self::camposBase()),

                                    Builder\Block::make('numero')
                                        ->label(fn (?array $state): string => 'Número: '.($state['etiqueta'] ?? '—'))
                                        ->schema([
                                            ...self::camposBase(),
                                            TextInput::make('unidad')
                                                ->label('Unidad (ej: €, m², km)')
                                                ->nullable(),
                                        ]),

                                    Builder\Block::make('select')
                                        ->label(fn (?array $state): string => 'Selección: '.($state['etiqueta'] ?? '—'))
                                        ->schema([
                                            ...self::camposBase(),
                                            Repeater::make('opciones')
                                                ->label('Opciones del desplegable')
                                                ->simple(TextInput::make('valor')->required())
                                                ->minItems(2)
                                                ->reorderable()
                                                ->cloneable()
                                                ->helperText('Mínimo 2 opciones.'),
                                        ]),

                                    Builder\Block::make('booleano')
                                        ->label(fn (?array $state): string => 'Sí/No: '.($state['etiqueta'] ?? '—'))
                                        ->schema(self::camposBase()),

                                    Builder\Block::make('fecha')
                                        ->label(fn (?array $state): string => 'Fecha: '.($state['etiqueta'] ?? '—'))
                                        ->schema(self::camposBase()),

                                    Builder\Block::make('escala')
                                        ->label(fn (?array $state): string => 'Escala: '.($state['etiqueta'] ?? '—'))
                                        ->schema([
                                            ...self::camposBase(),
                                            Select::make('tipo_escala_id')
                                                ->label('Escala del catálogo')
                                                ->options(fn () => \Modules\Escalas\Models\TipoEscala::pluck('nombre', 'id'))
                                                ->searchable()
                                                ->required()
                                                ->helperText('Solo se capturará la puntuación total. El pase completo se realiza en el módulo Escalas.'),
                                        ]),
                                ])
                                ->collapsed()
                                ->collapsible()
                                ->cloneable()
                                ->reorderable()
                                ->blockNumbers(false)
                                ->afterStateHydrated(function (Builder $component, mixed $state): void {
                                    if (empty($state)) {
                                        $component->state([]);

                                        return;
                                    }

                                    // Si ya está en formato Builder (['type' => ..., 'data' => ...]), no transformar
                                    $first = is_array($state) ? reset($state) : null;
                                    if (is_array($first) && isset($first['type'])) {
                                        return;
                                    }

                                    // Viene del modelo: ['campos' => [...]]
                                    if (is_array($state) && isset($state['campos'])) {
                                        $builderState = collect($state['campos'])
                                            ->filter(fn ($c) => is_array($c))
                                            ->map(fn (array $campo) => [
                                                'type' => $campo['tipo'] ?? 'texto',
                                                'data' => $campo,
                                            ])
                                            ->values()
                                            ->all();

                                        $component->state($builderState);

                                        return;
                                    }

                                    $component->state([]);
                                })
                                ->dehydrateStateUsing(function (mixed $state): array {
                                    if (empty($state) || ! is_array($state)) {
                                        return ['campos' => []];
                                    }

                                    $idsUsados = [];

                                    $campos = collect($state)
                                        ->filter(fn ($block) => is_array($block) && isset($block['type']))
                                        ->values()
                                        ->map(function (array $block, int $i) use (&$idsUsados): ?array {
                                            $tipo = $block['type'];
                                            $data = $block['data'] ?? [];

                                            if (! is_array($data)) {
                                                return null;
                                            }

                                            // Generar id desde etiqueta si no existe
                                            if (empty($data['id'])) {
                                                $base = Str::slug($data['etiqueta'] ?? 'campo', '_');
                                                $id   = $base;
                                                $n    = 1;
                                                while (in_array($id, $idsUsados, true)) {
                                                    $id = $base.'_'.$n;
                                                    $n++;
                                                }
                                                $data['id'] = $id;
                                            }

                                            $idsUsados[] = $data['id'];
                                            $data['tipo']  = $tipo;
                                            $data['orden'] = $i + 1;

                                            return $data;
                                        })
                                        ->filter()
                                        ->values()
                                        ->all();

                                    return ['campos' => $campos];
                                }),
                        ]),
                ]),
        ]);
    }

    // -------------------------------------------------------------------------
    // Páginas
    // -------------------------------------------------------------------------

    /**
     * Devuelve las paginas registradas para el recurso.
     *
     * @return array<string, mixed> Rutas de paginas Filament.
     */
    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTipoFichas::route('/'),
            'create' => Pages\CreateTipoFicha::route('/create'),
            'edit'   => Pages\EditTipoFicha::route('/{record}/edit'),
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers públicos (usados por las páginas)
    // -------------------------------------------------------------------------

    /**
     * Convierte el estado crudo del Builder (bloques con 'type'/'data')
     * al formato canónico del schema del modelo ({'campos': [...]}).
     * Si el estado ya está en formato canónico, lo devuelve sin modificar.
     * Necesario porque en Filament 5 el valor de dehydrateStateUsing en
     * un Builder NO se asigna automáticamente a $data en mutateFormDataBefore*.
     *
     * @param mixed $state Estado crudo del Builder.
     * @return array<string, mixed> Schema canonico normalizado.
     */
    public static function convertirSchemaBlocks(mixed $state): array
    {
        if (is_array($state) && isset($state['campos'])) {
            return $state;
        }

        if (empty($state) || ! is_array($state)) {
            return ['campos' => []];
        }

        $idsUsados = [];

        $campos = collect($state)
            ->filter(fn ($block) => is_array($block) && isset($block['type']))
            ->values()
            ->map(function (array $block, int $i) use (&$idsUsados): ?array {
                $tipo = $block['type'];
                $data = $block['data'] ?? [];

                if (! is_array($data)) {
                    return null;
                }

                if (empty($data['id'])) {
                    $base = Str::slug($data['etiqueta'] ?? 'campo', '_');
                    $id   = $base;
                    $n    = 1;
                    while (in_array($id, $idsUsados, true)) {
                        $id = $base.'_'.$n;
                        $n++;
                    }
                    $data['id'] = $id;
                }

                $idsUsados[] = $data['id'];
                $data['tipo']  = $tipo;
                $data['orden'] = $i + 1;

                return $data;
            })
            ->filter()
            ->values()
            ->all();

        return ['campos' => $campos];
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    /** Campos base compartidos por todos los bloques del Builder. */
    private static function camposBase(): array
    {
        return [
            TextInput::make('etiqueta')
                ->label('Etiqueta visible al profesional')
                ->required(),

            Textarea::make('descripcion')
                ->label('Texto de ayuda (opcional)')
                ->rows(2)
                ->nullable(),

            Toggle::make('obligatorio')
                ->label('Campo obligatorio')
                ->default(false),
        ];
    }
}
