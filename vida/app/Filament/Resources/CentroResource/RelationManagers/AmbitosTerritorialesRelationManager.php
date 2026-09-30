<?php

namespace App\Filament\Resources\CentroResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Centro\Models\AmbitoTerritorial;
use Modules\Organizacion\Models\Barrio;
use Modules\Organizacion\Models\Distrito;
use Modules\Organizacion\Models\SeccionCensal;

/**
 * Gestor de relación de ámbitos territoriales de un centro.
 *
 * Cada ámbito se elige del catálogo territorial (distrito, barrio o sección
 * censal) con un selector filtrable. Las reglas de coherencia y de
 * solapamiento viven en el modelo; aquí se muestran como aviso en lugar de
 * como error de servidor.
 */
class AmbitosTerritorialesRelationManager extends RelationManager
{
    protected static string $relationship = 'ambitosTeritoriales';

    protected static ?string $title = 'Ámbito territorial';

    /** @var array<string, string> */
    private const ETIQUETAS_TIPO = [
        'ciudad_completa' => 'Ciudad completa',
        'demarcacion_oficial' => 'Distrito',
        'barrios' => 'Barrio',
        'secciones_censales' => 'Sección censal',
        'poligono_gis' => 'Polígono GIS (no se usa para asignar)',
    ];

    /**
     * Define el formulario del relation manager de ámbitos territoriales.
     *
     * @param Schema $schema Esquema base.
     * @return Schema
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ámbito territorial')
                ->columns(2)
                ->schema([
                    Select::make('tipo')
                        ->label('Tipo')
                        ->options(self::ETIQUETAS_TIPO)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set): void {
                            $set('referencia_id', null);
                            $set('referencia_tipo', AmbitoTerritorial::CLASE_REFERENCIA[$state] ?? null);
                        }),

                    Select::make('referencia_id')
                        ->label('Unidad territorial')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search, Get $get) => self::buscarUnidades($get('tipo'), $search))
                        ->getOptionLabelUsing(fn ($value, Get $get) => self::etiquetaUnidad($get('tipo'), (int) $value))
                        ->visible(fn (Get $get) => isset(AmbitoTerritorial::CLASE_REFERENCIA[$get('tipo')]))
                        ->required(fn (Get $get) => isset(AmbitoTerritorial::CLASE_REFERENCIA[$get('tipo')]))
                        ->live()
                        ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                            if ($state !== null && blank($get('descripcion'))) {
                                $set('descripcion', self::etiquetaUnidad($get('tipo'), (int) $state));
                            }
                        }),

                    Hidden::make('referencia_tipo'),

                    TextInput::make('descripcion')
                        ->label('Descripción')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Se rellena con el nombre de la unidad; se puede cambiar.'),

                    Textarea::make('geojson')
                        ->label('GeoJSON')
                        ->nullable()
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => $get('tipo') === 'poligono_gis')
                        ->helperText('JSON con la geometría del polígono. No se usa para asignar personas en la v1.'),
                ]),
        ]);
    }

    /**
     * Define la tabla del relation manager de ámbitos territoriales.
     *
     * @param Table $table Tabla base.
     * @return Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => self::ETIQUETAS_TIPO[$state] ?? ucfirst($state)),

                Tables\Columns\TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->searchable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(fn (array $data, CreateAction $action) => $this->guardar(
                        fn () => $this->getRelationship()->create($data),
                        $action,
                    )),
            ])
            ->actions([
                EditAction::make()
                    ->using(fn (Model $record, array $data, EditAction $action) => $this->guardar(
                        fn () => tap($record)->update($data),
                        $action,
                    )),
                DeleteAction::make(),
            ]);
    }

    /**
     * Ejecuta el guardado y convierte las reglas rechazadas por el modelo en un
     * aviso al usuario, sin cerrar el formulario.
     *
     * @param callable(): Model $guardar
     * @param Action $action Acción en curso, para detenerla si se rechaza.
     * @return Model
     */
    private function guardar(callable $guardar, Action $action): Model
    {
        try {
            return $guardar();
        } catch (\InvalidArgumentException $e) {
            Notification::make()->title('No se puede guardar el ámbito')->body($e->getMessage())->danger()->send();
            $action->halt();
        }
    }

    /**
     * Busca unidades del tipo indicado por código o nombre (máximo 50).
     *
     * @param string|null $tipo Tipo de ámbito.
     * @param string $texto Texto de búsqueda.
     * @return array<int, string>
     */
    private static function buscarUnidades(?string $tipo, string $texto): array
    {
        $like = '%'.$texto.'%';

        $consulta = match ($tipo) {
            'demarcacion_oficial' => Distrito::query()
                ->where(fn ($q) => $q->where('codigo', 'ilike', $like)->orWhere('nombre', 'ilike', $like))
                ->orderBy('codigo'),
            'barrios' => Barrio::query()->with('distrito')
                ->where(fn ($q) => $q->where('codigo', 'ilike', $like)->orWhere('nombre', 'ilike', $like))
                ->orderBy('codigo'),
            'secciones_censales' => SeccionCensal::query()->with('barrio')
                ->where(fn ($q) => $q->where('codigo_ine', 'ilike', $like)
                    ->orWhereHas('barrio', fn ($b) => $b->where('nombre', 'ilike', $like)))
                ->orderBy('codigo_ine'),
            default => null,
        };

        return $consulta?->limit(50)->get()
            ->mapWithKeys(fn (Model $unidad) => [$unidad->getKey() => self::etiqueta($unidad)])
            ->all() ?? [];
    }

    /**
     * Etiqueta legible de una unidad por tipo de ámbito e id.
     *
     * @param string|null $tipo Tipo de ámbito.
     * @param int $id Id de la unidad.
     * @return string|null
     */
    private static function etiquetaUnidad(?string $tipo, int $id): ?string
    {
        $clase = AmbitoTerritorial::CLASE_REFERENCIA[$tipo] ?? null;
        $unidad = $clase ? $clase::find($id) : null;

        return $unidad ? self::etiqueta($unidad) : null;
    }

    /**
     * Texto que identifica una unidad territorial en el selector.
     *
     * @param Model $unidad Distrito, barrio o sección censal.
     * @return string
     */
    private static function etiqueta(Model $unidad): string
    {
        return match (true) {
            $unidad instanceof Distrito => "Distrito {$unidad->codigo} — {$unidad->nombre}",
            $unidad instanceof Barrio => "Barrio {$unidad->codigo} — {$unidad->nombre}",
            $unidad instanceof SeccionCensal => "Sección {$unidad->codigo_ine}".($unidad->barrio ? " ({$unidad->barrio->nombre})" : ''),
            default => (string) $unidad->getKey(),
        };
    }
}
