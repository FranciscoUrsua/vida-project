<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\ServicioEmergenciaResource\Pages;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Organizacion\Models\ServicioEmergenciaPreautorizado;

/**
 * Backoffice: gestión del catálogo de servicios de emergencia preautorizados.
 *
 * Los servicios aquí listados tienen acceso en modo consulta a Historias
 * de ciudadanos protegidos sin aprobación previa (excepción de urgencia).
 *
 * Accesible en /admin/servicios-emergencia.
 */
class ServicioEmergenciaResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = ServicioEmergenciaPreautorizado::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Servicios de emergencia';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?string $modelLabel = 'Servicio de emergencia';

    protected static ?string $pluralModelLabel = 'Servicios de emergencia';

    protected static ?string $slug = 'servicios-emergencia';

    protected static ?int $navigationSort = 3;

    /**
     * Define el formulario de servicios de emergencia.
     *
     * @param Schema $schema Esquema base del formulario.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos del servicio')
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre del servicio')
                        ->required()
                        ->maxLength(150),

                    TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(100)
                        ->helperText('Identificador estable en minúsculas con guiones. Ej: samur-social'),

                    Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(3),

                    Toggle::make('activo')
                        ->label('Activo')
                        ->default(true),
                ]),
        ]);
    }

    /**
     * Configura el listado de servicios de emergencia.
     *
     * @param Table $table Tabla base.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Servicio')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')->label('Estado'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('nombre');
    }

    /**
     * Declara las páginas del catálogo de servicios de emergencia.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiciosEmergencia::route('/'),
            'create' => Pages\CreateServicioEmergencia::route('/create'),
            'edit' => Pages\EditServicioEmergencia::route('/{record}/edit'),
        ];
    }
}
