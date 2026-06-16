<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoRelacionResource\Pages;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Ciudadania\Enums\ImplicacionFuncional;
use Modules\Ciudadania\Models\TipoRelacion;

/**
 * Backoffice: gestión del catálogo de tipos de relación entre ciudadanos.
 *
 * Accesible en /admin/tipos-relacion.
 * Restringido a adm_sistema. Los tipos del seeder (eliminable = false) no
 * muestran el botón de eliminar.
 */
class TipoRelacionResource extends Resource
{
    protected static ?string $model = TipoRelacion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Tipos de relación';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?string $modelLabel = 'Tipo de relación';

    protected static ?string $pluralModelLabel = 'Tipos de relación';

    protected static ?int $navigationSort = 9;

    // -------------------------------------------------------------------------
    // Formulario
    // -------------------------------------------------------------------------

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Identificación')
                ->schema([
                    TextInput::make('slug')
                        ->label('Slug (identificador interno)')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->alphaDash()
                        ->maxLength(50)
                        ->helperText('Solo letras, números y guiones bajos. No editable tras la creación.')
                        ->disabled(fn ($record) => $record !== null),

                    TextInput::make('etiqueta')
                        ->label('Etiqueta (visible para el TSR)')
                        ->required()
                        ->maxLength(80),

                    TextInput::make('etiqueta_reciproca')
                        ->label('Etiqueta del tipo recíproco')
                        ->required()
                        ->maxLength(80)
                        ->helperText('Cómo se etiqueta la relación inversa (p. ej. si este tipo es "Padre/Madre", el recíproco es "Hijo/a").'),
                ])->columns(3),

            Section::make('Reciprocidad')
                ->schema([
                    Toggle::make('simetrica')
                        ->label('Relación simétrica')
                        ->helperText('Marca si ambas partes tienen el mismo rol (cónyuge, hermano/a). En ese caso no hace falta definir un slug recíproco.')
                        ->live(),

                    Select::make('slug_reciproco')
                        ->label('Tipo recíproco')
                        ->options(fn () => TipoRelacion::orderBy('etiqueta')->pluck('etiqueta', 'slug'))
                        ->searchable()
                        ->nullable()
                        ->hidden(fn (Get $get) => (bool) $get('simetrica'))
                        ->helperText('Tipo de relación que se crea automáticamente en la otra parte.'),
                ])->columns(2),

            Section::make('Implicación funcional')
                ->schema([
                    Select::make('implicacion_funcional')
                        ->label('Implicación funcional')
                        ->options(
                            collect(ImplicacionFuncional::cases())
                                ->mapWithKeys(fn ($case) => [$case->value => $case->etiqueta()])
                                ->toArray()
                        )
                        ->nullable()
                        ->helperText('Si este tipo tiene relevancia en procesos del sistema, selecciona la implicación correspondiente.'),

                    Toggle::make('activo')
                        ->label('Activo')
                        ->default(true),
                ])->columns(2),

        ]);
    }

    // -------------------------------------------------------------------------
    // Tabla
    // -------------------------------------------------------------------------

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->fontFamily('mono')
                    ->width('140px'),

                Tables\Columns\TextColumn::make('etiqueta')
                    ->label('Etiqueta')
                    ->searchable(),

                Tables\Columns\TextColumn::make('etiqueta_reciproca')
                    ->label('Recíproco'),

                Tables\Columns\IconColumn::make('simetrica')
                    ->label('Simétrica')
                    ->boolean()
                    ->width('80px'),

                Tables\Columns\TextColumn::make('implicacion_funcional')
                    ->label('Implicación funcional')
                    ->badge()
                    ->color(fn ($state) => $state ? 'warning' : 'gray')
                    ->formatStateUsing(fn ($state) => $state
                        ? ImplicacionFuncional::from($state)->etiqueta()
                        : '—'
                    ),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean()
                    ->width('70px'),

                Tables\Columns\IconColumn::make('eliminable')
                    ->label('Del sistema')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-pencil')
                    ->trueColor('gray')
                    ->falseColor('success')
                    ->width('90px'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')->label('Estado'),
                Tables\Filters\TernaryFilter::make('simetrica')->label('Simétrica'),
                Tables\Filters\SelectFilter::make('implicacion_funcional')
                    ->label('Implicación funcional')
                    ->options(
                        collect(ImplicacionFuncional::cases())
                            ->mapWithKeys(fn ($case) => [$case->value => $case->etiqueta()])
                            ->toArray()
                    ),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn ($record) => $record->eliminable),
            ])
            ->defaultSort('etiqueta');
    }

    // -------------------------------------------------------------------------
    // Autorización
    // -------------------------------------------------------------------------

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('adm_sistema') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('adm_sistema') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasRole('adm_sistema') ?? false;
    }

    public static function canDelete($record): bool
    {
        return (auth()->user()?->hasRole('adm_sistema') ?? false) && $record->eliminable;
    }

    // -------------------------------------------------------------------------
    // Páginas
    // -------------------------------------------------------------------------

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTiposRelacion::route('/'),
            'create' => Pages\CreateTipoRelacion::route('/create'),
            'edit'   => Pages\EditTipoRelacion::route('/{record}/edit'),
        ];
    }
}
