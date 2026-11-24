<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParametroInformeResource\Pages;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Documentos\Models\ParametroInforme;

/**
 * Gestión de los parámetros configurables de las plantillas de informe (variables
 * auxiliares): merge tags con valor fijo que define el administrador.
 *
 * Solo `adm_sistema` crea, edita y borra; la supervisión puede consultarlos.
 */
class ParametroInformeResource extends Resource
{
    protected static ?string $model = ParametroInforme::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-variable';

    protected static ?string $navigationLabel = 'Parámetros de informe';

    protected static string|\UnitEnum|null $navigationGroup = 'Informes y Plantillas';

    protected static ?string $modelLabel = 'Parámetro de informe';

    protected static ?string $pluralModelLabel = 'Parámetros de informe';

    protected static ?int $navigationSort = 5;

    /**
     * Define el formulario del parámetro.
     *
     * @param Schema $schema Esquema base del formulario.
     *
     * @return Schema
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('clave')
                ->label('Clave (nombre del tag)')
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true)
                ->regex(ParametroInforme::REGEX_CLAVE)
                ->validationMessages(['regex' => 'Solo minúsculas, números y guiones bajos, empezando por una letra.'])
                ->helperText('Solo minúsculas, números y guiones bajos. Ejemplo: ciudad, web_municipal')
                ->prefix('{{ ')
                ->suffix(' }}')
                ->columnSpan(1),

            TextInput::make('etiqueta')
                ->label('Etiqueta en el editor')
                ->required()
                ->maxLength(200)
                ->helperText('Texto legible que aparece en el catálogo de variables.')
                ->columnSpan(1),

            Textarea::make('valor')
                ->label('Valor')
                ->required()
                ->rows(2)
                ->columnSpanFull(),

            Textarea::make('descripcion')
                ->label('Descripción')
                ->rows(2)
                ->helperText('Para qué se usa este parámetro. Solo visible en el backoffice.')
                ->nullable()
                ->columnSpanFull(),
        ]);
    }

    /**
     * Define la tabla de parámetros.
     *
     * @param Table $table Tabla base.
     *
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('clave')
                    ->label('Clave')
                    ->badge()
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('etiqueta')
                    ->label('Etiqueta')
                    ->searchable(),
                Tables\Columns\TextColumn::make('valor')
                    ->label('Valor')
                    ->limit(60),
                Tables\Columns\TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make()->authorize(fn (Model $record) => static::canEdit($record)),
                DeleteAction::make()->authorize(fn (Model $record) => static::canDelete($record)),
            ])
            ->defaultSort('clave');
    }

    /**
     * Declara las páginas del recurso.
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParametrosInforme::route('/'),
            'create' => Pages\CreateParametroInforme::route('/create'),
            'view' => Pages\ViewParametroInforme::route('/{record}'),
            'edit' => Pages\EditParametroInforme::route('/{record}/edit'),
        ];
    }

    /**
     * Pueden ver el listado la administración del sistema y la supervisión.
     *
     * @return bool
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema', 'supervision']) ?? false;
    }

    /**
     * Solo `adm_sistema` crea parámetros.
     *
     * @return bool
     */
    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('adm_sistema') ?? false;
    }

    /**
     * Solo `adm_sistema` edita parámetros.
     *
     * @param Model $record Registro objetivo.
     *
     * @return bool
     */
    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasRole('adm_sistema') ?? false;
    }

    /**
     * Solo `adm_sistema` borra parámetros.
     *
     * @param Model $record Registro objetivo.
     *
     * @return bool
     */
    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }
}
