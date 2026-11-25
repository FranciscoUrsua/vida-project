<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\AreaEncargoResource\Pages;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Documentos\Models\PlantillaInforme;
use Modules\Documentos\Support\InformeEncargo;
use Modules\Intervencion\Models\AreaEncargo;

/**
 * Recurso Filament de las áreas del encargo profesional y su caja de herramientas (EA-1, EA-2
 * de docs/instrucciones-cli/2026-11-encargo-actuaciones.md).
 *
 * Solo `adm_sistema` (AutorizaGestion). Las plantillas de informe que leen la Historia Social
 * no se pueden asociar (EA-9): el formulario lo valida con un mensaje claro y el pivote
 * AreaEncargoPlantillaInforme lo vuelve a impedir por si se llega por otro camino.
 */
class AreaEncargoResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = AreaEncargo::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'Áreas de encargo';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?string $modelLabel = 'Área de encargo';

    protected static ?string $pluralModelLabel = 'Áreas de encargo';

    protected static ?int $navigationSort = 12;

    /**
     * Formulario del área: identificación y caja de herramientas.
     *
     * @param Schema $schema
     *
     * @return Schema
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
                        ->alphaDash()
                        ->maxLength(60)
                        ->helperText('Identificador estable. No editable una vez creada.')
                        ->disabled(fn (?AreaEncargo $record) => $record !== null),

                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(120),

                    Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(2)
                        ->columnSpanFull(),

                    Toggle::make('activa')
                        ->label('Activa')
                        ->helperText('Solo las áreas activas se pueden elegir al encargar.')
                        ->default(true),
                ]),

            Section::make('Caja de herramientas')
                ->description('Lo que puede usar el profesional en los encargos de esta área. Las anotaciones, los documentos y las citas los tiene siempre.')
                ->schema([
                    CheckboxList::make('tiposFicha')
                        ->label('Fichas de valoración')
                        ->relationship('tiposFicha', 'nombre', fn (Builder $query) => $query->activos()->deValoracion())
                        ->columns(2),

                    CheckboxList::make('tiposEscala')
                        ->label('Escalas')
                        ->relationship('tiposEscala', 'nombre')
                        ->columns(2),

                    CheckboxList::make('plantillasInforme')
                        ->label('Plantillas de informe')
                        ->relationship('plantillasInforme', 'nombre', fn (Builder $query) => $query->where('activa', true))
                        ->columns(2)
                        ->helperText('Solo admite plantillas que no leen la Historia Social: fuentes de identificación, contacto, profesional y encargo.')
                        // Filament evalúa los closures de rules(): el exterior devuelve la regla de Laravel
                        ->rules([fn (): Closure => self::reglaPlantillas()]),
                ]),
        ]);
    }

    /**
     * Tabla de áreas.
     *
     * @param Table $table
     *
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->label('Código')->fontFamily('mono')->searchable(),
                TextColumn::make('nombre')->label('Nombre')->searchable(),
                TextColumn::make('tipos_ficha_count')->label('Fichas')->counts('tiposFicha'),
                TextColumn::make('tipos_escala_count')->label('Escalas')->counts('tiposEscala'),
                TextColumn::make('plantillas_informe_count')->label('Plantillas')->counts('plantillasInforme'),
                IconColumn::make('activa')->label('Activa')->boolean(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (AreaEncargo $record) => ! $record->encargos()->withTrashed()->exists()),
            ]);
    }

    /**
     * Regla de validación: ninguna plantilla elegida puede leer la Historia Social (EA-9).
     *
     * @return Closure(string, mixed, Closure): void
     */
    public static function reglaPlantillas(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $plantillas = PlantillaInforme::whereKey((array) $value)->get();

            foreach ($plantillas as $plantilla) {
                $infracciones = InformeEncargo::infracciones($plantilla);

                if ($infracciones !== []) {
                    $fail("«{$plantilla->nombre}» no sirve para un encargo: ".implode('; ', $infracciones).'.');
                }
            }
        };
    }

    /**
     * Páginas del recurso.
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAreasEncargo::route('/'),
            'create' => Pages\CreateAreaEncargo::route('/create'),
            'edit' => Pages\EditAreaEncargo::route('/{record}/edit'),
        ];
    }
}
