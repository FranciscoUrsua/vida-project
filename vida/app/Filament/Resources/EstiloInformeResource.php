<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\EstiloInformeResource\Pages;
use App\Models\UnidadOrganizativa;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Documentos\Models\EstiloInforme;

/**
 * Gestión del estilo formal de informes por Unidad Organizativa.
 *
 * Los campos se heredan campo a campo por la jerarquía de UOs.
 * Accesible solo a usuarios con rol supervisor o admin_sistema.
 * Cada supervisor solo puede editar los estilos de su UO y descendientes.
 *
 * El logotipo no se gestiona aquí: es único para toda la organización y se
 * sube desde Sistema → Configuración (ver docs/decisiones-tecnicas.md Sección 12).
 */
class EstiloInformeResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = EstiloInforme::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationLabel = 'Estilos de informe';

    protected static string|\UnitEnum|null $navigationGroup = 'Informes y Plantillas';

    protected static ?string $modelLabel = 'Estilo de informe';

    protected static ?string $pluralModelLabel = 'Estilos de informe';

    protected static ?int $navigationSort = 2;

    /**
     * Define el formulario de estilos de informe.
     *
     * @param Schema $schema Esquema base del formulario.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Unidad Organizativa')
                ->schema([
                    Select::make('unidad_organizativa_id')
                        ->label('Unidad Organizativa')
                        ->options(fn () => UnidadOrganizativa::where('activa', true)
                            ->orderBy('nombre')
                            ->pluck('nombre', 'id'))
                        ->searchable()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Solo puede existir un estilo por UO.'),
                ]),

            Section::make('Cabecera')
                ->columns(2)
                ->schema([
                    TextInput::make('nombre_unidad_cabecera')
                        ->label('Nombre de la unidad')
                        ->maxLength(200)
                        ->nullable()
                        ->helperText('Ej: Centro de Servicios Sociales de Vallecas.'),

                    TextInput::make('telefono_cabecera')
                        ->label('Teléfono')
                        ->tel()
                        ->maxLength(30)
                        ->nullable(),

                    TextInput::make('direccion_cabecera')
                        ->label('Dirección')
                        ->maxLength(300)
                        ->nullable()
                        ->columnSpanFull(),

                    Placeholder::make('logo_organizacion_aviso')
                        ->label('Logotipo')
                        ->columnSpanFull()
                        ->content('El logotipo es único para toda la organización y se sube desde Sistema → Configuración → «Identidad visual». Se usa tanto en la aplicación como en la cabecera de los informes.'),
                ]),

            Section::make('Pie de página')
                ->schema([
                    Textarea::make('html_pie')
                        ->label('HTML del pie de página')
                        ->rows(4)
                        ->nullable()
                        ->helperText('HTML libre: puede incluir textos legales, URLs, información de contacto.')
                        // El marcador se sustituye por el número de página real al generar el
                        // PDF (ServicioGeneracionPDF), no en este formulario.
                        ->hintAction(
                            Action::make('insertarNumeroPagina')
                                ->label('Insertar número de página')
                                ->icon('heroicon-o-hashtag')
                                ->action(function (Set $set, Get $get): void {
                                    $actual = $get('html_pie') ?? '';
                                    $set('html_pie', rtrim($actual.' '.EstiloInforme::MARCADOR_NUMERO_PAGINA));
                                })
                        ),
                ]),
        ]);
    }

    /**
     * Configura el listado de estilos de informe.
     *
     * @param Table $table Tabla base.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('unidadOrganizativa.nombre')
                    ->label('Unidad Organizativa')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('nombre_unidad_cabecera')
                    ->label('Nombre cabecera')
                    ->placeholder('—')
                    ->limit(40),

                Tables\Columns\TextColumn::make('direccion_cabecera')
                    ->label('Dirección')
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(),

                Tables\Columns\IconColumn::make('html_pie')
                    ->label('Pie HTML')
                    ->getStateUsing(fn (EstiloInforme $r) => $r->html_pie !== null)
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('creadoPor.name')
                    ->label('Creado por')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->authorize(fn (Model $record) => static::canDelete($record)),
            ])
            ->defaultSort('unidadOrganizativa.nombre');
    }

    /**
     * Declara las páginas del recurso de estilos de informe.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEstilosInforme::route('/'),
            'create' => Pages\CreateEstiloInforme::route('/create'),
            'edit' => Pages\EditEstiloInforme::route('/{record}/edit'),
        ];
    }

    /**
     * Determina si el usuario puede ver el listado de estilos de informe.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema', 'adm_usuarios', 'supervision']) ?? false;
    }

    /**
     * Determina si el usuario puede editar un estilo de informe.
     *
     * @param Model $record Registro objetivo.
     */
    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema', 'adm_usuarios']) ?? false;
    }

    /**
     * Determina si el usuario puede eliminar un estilo de informe.
     *
     * @param Model $record Registro objetivo.
     */
    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }
}
