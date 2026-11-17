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
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Modules\Escalas\Models\TipoEscala;
use Modules\Intervencion\Models\TipoFicha;

/**
 * Recurso Filament para la gestión de fichas de valoración configurables.
 *
 * Cada TipoFicha define un formulario con campos tipados: texto, número, select,
 * booleano, fecha y escala.
 *
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
     * Configura el listado de tipos de ficha.
     *
     * @param Table $table Tabla base.
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

                TextColumn::make('uso')
                    ->label('Uso')
                    ->formatStateUsing(fn (string $state): string => $state === 'solicitud' ? 'Solicitud de prestación' : 'Valoración'),

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
                SelectFilter::make('uso')
                    ->label('Uso')
                    ->options(['valoracion' => 'Valoración', 'solicitud' => 'Solicitud de prestación']),
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
     * Define el formulario de tipos de ficha.
     *
     * @param Schema $schema Esquema base del formulario.
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

                                    // Fase 3 bis: los formularios de solicitud de prestación usan el mismo mecanismo
                                    Select::make('uso')
                                        ->label('Uso')
                                        ->options(['valoracion' => 'Ficha de valoración', 'solicitud' => 'Formulario de solicitud de prestación'])
                                        ->default('valoracion')
                                        ->required(),

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
                                                ->options(fn () => TipoEscala::pluck('nombre', 'id'))
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

                                    // Si ya está en formato Builder (['type' => ..., 'data' => ...])
                                    // viene de una re-hidratación de Livewire (p.ej. tras reordenar).
                                    // Normalizar opciones de bloques select a strings planos para que
                                    // el afterStateHydrated propio del Repeater::simple() las envuelva
                                    // correctamente en lugar de hacer doble-wrapping.
                                    $first = is_array($state) ? reset($state) : null;
                                    if (is_array($first) && isset($first['type'])) {
                                        $normalized = collect($state)->map(function (array $block) {
                                            if (($block['type'] ?? '') === 'select'
                                                && isset($block['data']['opciones'])
                                                && is_array($block['data']['opciones'])
                                            ) {
                                                $block['data']['opciones'] = self::normalizarOpcionesParaSchema(
                                                    $block['data']['opciones']
                                                );
                                            }

                                            return $block;
                                        })->all();
                                        $component->rawState($normalized);

                                        return;
                                    }

                                    // Viene del modelo: ['campos' => [...]]
                                    if (is_array($state) && isset($state['campos'])) {
                                        /** @var array<int, mixed> $camposState */
                                        $camposState = $state['campos'];

                                        $builderState = collect($camposState)
                                            ->filter(fn ($c) => is_array($c))
                                            ->map(fn (array $campo) => [
                                                'type' => $campo['tipo'] ?? 'texto',
                                                'data' => self::normalizarOpcionesParaBuilder($campo),
                                            ])
                                            ->values()
                                            ->all();

                                        $component->state($builderState);

                                        return;
                                    }

                                    $component->state([]);
                                }),
                            // Sin dehydrateStateUsing a propósito: Filament reindexa como lista lo que
                            // devuelve y la clave 'campos' se perdía, con lo que se guardaba la ficha sin
                            // campos (incidencia del 17 nov 2026). La conversión al schema canónico la
                            // hacen solo las páginas, con convertirSchemaBlocks().
                        ]),
                ]),
        ]);
    }

    // -------------------------------------------------------------------------
    // Páginas
    // -------------------------------------------------------------------------

    /**
     * Declara las páginas del catálogo de tipos de ficha.
     *
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTipoFichas::route('/'),
            'create' => Pages\CreateTipoFicha::route('/create'),
            'edit' => Pages\EditTipoFicha::route('/{record}/edit'),
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers públicos (usados por las páginas)
    // -------------------------------------------------------------------------

    /**
     * Convierte el estado crudo del Builder (bloques `type`/`data`, con clave uuid) al
     * schema canónico del modelo (`['campos' => [...]]`). Es la única conversión: la
     * llaman las páginas de creación y edición en `mutateFormDataBefore*()`. Si ya llega
     * en formato canónico, lo devuelve tal cual.
     *
     * Un bloque con otra forma es un error, no un campo que se descarta: descartarlo en
     * silencio guardaba la ficha sin campos.
     *
     * @param mixed $state Estado crudo del Builder.
     *
     * @return array{campos: array<int, array<string, mixed>>}
     *
     * @throws \LogicException Si algún bloque no tiene `type` y `data`.
     */
    public static function convertirSchemaBlocks(mixed $state): array
    {
        if (is_array($state) && isset($state['campos'])) {
            /** @var array{campos: array<int, array<string, mixed>>} $state */
            return $state;
        }

        if (empty($state) || ! is_array($state)) {
            return ['campos' => []];
        }

        $idsUsados = [];
        $campos = [];

        foreach (array_values($state) as $i => $block) {
            if (! is_array($block) || ! isset($block['type']) || ! is_array($block['data'] ?? null)) {
                throw new \LogicException('El Builder de campos ha enviado un bloque con una forma inesperada: no se guarda para no perder campos.');
            }

            $tipo = $block['type'];
            $data = $block['data'];

            // Id estable desde la etiqueta si el campo es nuevo
            if (empty($data['id'])) {
                $base = Str::slug($data['etiqueta'] ?? 'campo', '_');
                $id = $base;
                $n = 1;
                while (in_array($id, $idsUsados, true)) {
                    $id = $base.'_'.$n;
                    $n++;
                }
                $data['id'] = $id;
            }

            $idsUsados[] = $data['id'];
            $data['tipo'] = $tipo;
            $data['orden'] = $i + 1;

            // Opciones de select como array plano de strings
            if ($tipo === 'select' && isset($data['opciones']) && is_array($data['opciones'])) {
                $data['opciones'] = self::normalizarOpcionesParaSchema($data['opciones']);
            }

            $campos[] = $data;
        }

        return ['campos' => $campos];
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    /**
     * Transforma las opciones de un campo select de strings planos al formato
     * interno que espera Repeater::simple(): [['valor' => '...'], ...].
     * Necesario porque el afterStateHydrated propio del Repeater no se ejecuta
     * cuando el Builder carga el estado programáticamente.
     *
     * @param array<mixed> $campo
     *
     * @return array<string, mixed>
     */
    private static function normalizarOpcionesParaBuilder(array $campo): array
    {
        if (($campo['tipo'] ?? '') === 'select' && isset($campo['opciones']) && is_array($campo['opciones'])) {
            $campo['opciones'] = collect($campo['opciones'])
                ->map(fn ($opt) => is_array($opt) ? $opt : ['valor' => (string) $opt])
                ->values()
                ->all();
        }

        return $campo;
    }

    /**
     * Normaliza las opciones de un campo select desde el formato interno del
     * Repeater ([['valor' => '...'], ...]) a strings planos (['...', ...]).
     * Se aplica en convertirSchemaBlocks y al rehidratar el Builder, como defensa
     * ante ambos formatos posibles.
     *
     * @param array<mixed> $opciones
     *
     * @return array<string>
     */
    private static function normalizarOpcionesParaSchema(array $opciones): array
    {
        return collect($opciones)
            ->map(fn ($opt) => is_array($opt) ? ($opt['valor'] ?? '') : (string) $opt)
            ->filter(fn ($opt) => $opt !== '')
            ->values()
            ->all();
    }

    /**
     * Campos base compartidos por todos los bloques del Builder.
     *
     * @return array<int, Component>
     */
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
