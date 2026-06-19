<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\PlantillaInformeResource\Pages;
use App\Models\UnidadOrganizativa;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
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
use Illuminate\Database\Eloquent\Model;
use Modules\Documentos\Enums\TipoInforme;
use Modules\Documentos\Models\PlantillaInforme;
use Modules\Documentos\Support\MergeTagsCatalogo;

/**
 * Gestión de plantillas de informe profesional.
 *
 * Las plantillas tienen alcance jerárquico: se crean al nivel de UO adecuado
 * y son visibles para todos los profesionales de esa UO y sus descendientes.
 * Accesible solo a usuarios con rol supervisor o admin_sistema.
 */
class PlantillaInformeResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = PlantillaInforme::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Plantillas de informe';

    protected static string|\UnitEnum|null $navigationGroup = 'Informes y Plantillas';

    protected static ?string $modelLabel = 'Plantilla de informe';

    protected static ?string $pluralModelLabel = 'Plantillas de informe';

    protected static ?int $navigationSort = 1;

    /**
     * Define el formulario de plantillas de informe.
     *
     * @param Schema $schema Esquema base del formulario.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Identificación')
                ->columns(2)
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre de la plantilla')
                        ->required()
                        ->maxLength(200)
                        ->placeholder('Ej: Informe Social de Valoración')
                        ->columnSpanFull(),

                    Select::make('tipo_informe')
                        ->label('Tipo de informe')
                        ->options(collect(TipoInforme::cases())->mapWithKeys(
                            fn (TipoInforme $t) => [$t->value => $t->label()]
                        ))
                        ->required(),

                    Select::make('unidad_organizativa_id')
                        ->label('Unidad Organizativa de alcance')
                        ->options(function () {
                            $user = auth()->user();
                            $base = UnidadOrganizativa::where('activa', true)->orderBy('nombre');
                            if ($user?->hasRole('adm_sistema')) {
                                return $base->pluck('nombre', 'id');
                            }
                            $uoIds = $user?->uoSubtreeIds() ?? [];

                            return $base->whereIn('id', $uoIds)->pluck('nombre', 'id');
                        })
                        ->searchable()
                        ->required()
                        ->helperText('La plantilla estará disponible para esta UO y todas sus descendientes.'),

                    Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(2)
                        ->nullable()
                        ->columnSpanFull()
                        ->helperText('Texto de ayuda que verá el profesional en el selector de plantillas.'),
                ]),

            Section::make('Secciones del informe')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('secciones')
                        ->label('Secciones')
                        ->itemLabel(fn (array $state): ?string => filled($state['titulo'] ?? null)
                                ? $state['titulo']
                                : 'Nueva sección'
                        )
                        ->schema([
                            TextInput::make('id')
                                ->label('Identificador')
                                ->required()
                                ->placeholder('Ej: situacion_actual')
                                ->helperText('Identificador único de la sección. Sin espacios ni acentos.'),

                            TextInput::make('titulo')
                                ->label('Título')
                                ->required()
                                ->placeholder('Ej: Situación actual')
                                ->live(onBlur: true),

                            Select::make('tipo')
                                ->label('Tipo de sección')
                                ->options([
                                    'automatico' => 'Automática (datos de Historia Social)',
                                    'texto_libre' => 'Texto libre (redacción del profesional)',
                                ])
                                ->required()
                                ->live(),

                            Select::make('fuente')
                                ->label('Fuente de datos')
                                ->required()
                                ->options([
                                    'ciudadano.datos_basicos' => 'Ciudadano — Datos básicos (nombre, NIF, fecha nacimiento, dirección)',
                                    'ciudadano.datos_contacto' => 'Ciudadano — Datos de contacto (teléfono, email)',
                                    'ciudadano.unidad_convivencia' => 'Ciudadano — Unidad de convivencia',
                                    'historia_social.resumen' => 'Historia Social — Resumen y motivo de apertura',
                                    'historia_social.prestaciones_activas' => 'Historia Social — Prestaciones activas del plan vigente',
                                    'historia_social.prestaciones_historico' => 'Historia Social — Historial completo de prestaciones',
                                    'historia_social.plan_activo' => 'Historia Social — Plan de intervención activo (objetivos)',
                                    'escalas.barthel_ultimo' => 'Escalas — Último pase Barthel (score e interpretación)',
                                    'escalas.pfeiffer_ultimo' => 'Escalas — Último pase Pfeiffer SPMSQ (score e interpretación)',
                                    'escalas.lawton_ultimo' => 'Escalas — Último pase Lawton-Brody (score e interpretación)',
                                    'escalas.historico_barthel' => 'Escalas — Histórico de pases Barthel',
                                    'profesional.datos' => 'Profesional — Datos del autor (nombre, cargo, colegiado, centro)',
                                ])
                                ->searchable()
                                ->native(false)
                                ->visible(fn (Get $get): bool => $get('tipo') === 'automatico'),

                            Toggle::make('editable')
                                ->label('Editable por el profesional')
                                ->default(false)
                                ->visible(fn (Get $get): bool => $get('tipo') === 'automatico'),

                            Textarea::make('instrucciones')
                                ->label('Instrucciones para el profesional')
                                ->rows(3)
                                ->nullable()
                                ->helperText('Texto de ayuda que verá el profesional al redactar esta sección.')
                                ->visible(fn (Get $get): bool => $get('tipo') === 'texto_libre'),

                            RichEditor::make('contenido_plantilla')
                                ->label('Contenido base de la sección')
                                ->hint('Escribe {{ para insertar una variable dinámica, o usa el botón «Insertar variable» de la barra de herramientas.')
                                ->hintIcon('heroicon-o-information-circle')
                                ->mergeTags(MergeTagsCatalogo::todos())
                                ->toolbarButtons([
                                    ['bold', 'italic', 'underline', 'strike'],
                                    ['h2', 'h3'],
                                    ['bulletList', 'orderedList', 'blockquote'],
                                    ['undo', 'redo'],
                                ])
                                ->nullable()
                                ->columnSpanFull()
                                ->visible(fn (Get $get): bool => $get('tipo') === 'texto_libre'),

                            Toggle::make('obligatorio')
                                ->label('Sección obligatoria')
                                ->default(true)
                                ->helperText('El profesional no podrá firmar el informe si esta sección está vacía.')
                                ->visible(fn (Get $get): bool => $get('tipo') === 'texto_libre'),
                        ])
                        ->columns(1)
                        ->reorderableWithDragAndDrop()
                        ->collapsible()
                        ->cloneable()
                        ->addActionLabel('Añadir sección')
                        ->required(),
                ]),

            Section::make('Estado')
                ->schema([
                    Toggle::make('activa')
                        ->label('Activa')
                        ->default(false)
                        ->helperText('Solo las plantillas activas aparecen en el selector operativo.'),
                ]),
        ]);
    }

    /**
     * Configura el listado de plantillas de informe.
     *
     * @param Table $table Tabla base.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tipo_informe')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (TipoInforme $state) => $state->label())
                    ->color(fn (TipoInforme $state) => match ($state) {
                        TipoInforme::InformeSocial => 'info',
                        TipoInforme::InformePsicologico => 'warning',
                        TipoInforme::InformeJuridico => 'primary',
                        TipoInforme::Otro => 'gray',
                    }),

                Tables\Columns\TextColumn::make('unidadOrganizativa.nombre')
                    ->label('UO de alcance')
                    ->sortable(),

                Tables\Columns\IconColumn::make('activa')
                    ->label('Activa')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo_informe')
                    ->label('Tipo')
                    ->options(collect(TipoInforme::cases())->mapWithKeys(
                        fn (TipoInforme $t) => [$t->value => $t->label()]
                    )),

                Tables\Filters\SelectFilter::make('unidad_organizativa_id')
                    ->label('UO')
                    ->relationship('unidadOrganizativa', 'nombre'),

                Tables\Filters\TernaryFilter::make('activa')->label('Estado'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->authorize(fn () => auth()->user()?->hasAnyRole(['adm_sistema', 'adm_usuarios']) ?? false),
            ])
            ->defaultSort('nombre');
    }

    /**
     * Declara las páginas del recurso de plantillas de informe.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlantillasInforme::route('/'),
            'create' => Pages\CreatePlantillaInforme::route('/create'),
            'edit' => Pages\EditPlantillaInforme::route('/{record}/edit'),
        ];
    }

    /**
     * Determina si el usuario puede ver el listado de plantillas de informe.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema', 'adm_usuarios', 'supervision']) ?? false;
    }

    /**
     * Determina si el usuario puede editar una plantilla de informe.
     *
     * @param Model $record Registro objetivo.
     */
    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema', 'adm_usuarios']) ?? false;
    }

    /**
     * Determina si el usuario puede eliminar una plantilla de informe.
     *
     * @param Model $record Registro objetivo.
     */
    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema', 'adm_usuarios']) ?? false;
    }
}
