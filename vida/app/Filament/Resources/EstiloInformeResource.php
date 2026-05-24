<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EstiloInformeResource\Pages;
use App\Models\UnidadOrganizativa;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Documentos\Models\EstiloInforme;
use App\Filament\Concerns\AutorizaGestion;

/**
 * Gestión del estilo formal de informes por Unidad Organizativa.
 *
 * Los campos se heredan campo a campo por la jerarquía de UOs.
 * Accesible solo a usuarios con rol supervisor o admin_sistema.
 * Cada supervisor solo puede editar los estilos de su UO y descendientes.
 */
class EstiloInformeResource extends Resource
{
    use AutorizaGestion;
    protected static ?string $model = EstiloInforme::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';
    protected static ?string $navigationLabel = 'Estilos de informe';
    protected static string|\UnitEnum|null $navigationGroup = 'Informes y plantillas';
    protected static ?string $modelLabel = 'Estilo de informe';
    protected static ?string $pluralModelLabel = 'Estilos de informe';
    protected static ?int $navigationSort = 20;

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

                    TextInput::make('logo_cabecera')
                        ->label('Ruta al logotipo')
                        ->maxLength(500)
                        ->nullable()
                        ->columnSpanFull()
                        ->helperText('Ruta interna en el disco configurado al fichero de logotipo (PNG/SVG).'),
                ]),

            Section::make('Pie de página')
                ->schema([
                    Textarea::make('html_pie')
                        ->label('HTML del pie de página')
                        ->rows(4)
                        ->nullable()
                        ->helperText('HTML libre: puede incluir textos legales, URLs, información de contacto.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('unidadOrganizativa.nombre')
                    ->label('Unidad Organizativa')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\IconColumn::make('logo_cabecera')
                    ->label('Logo')
                    ->getStateUsing(fn (EstiloInforme $r) => $r->logo_cabecera !== null)
                    ->boolean()
                    ->alignCenter(),

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
                DeleteAction::make(),
            ])
            ->defaultSort('unidadOrganizativa.nombre');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEstilosInforme::route('/'),
            'create' => Pages\CreateEstiloInforme::route('/create'),
            'edit'   => Pages\EditEstiloInforme::route('/{record}/edit'),
        ];
    }
}
