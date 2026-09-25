<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoDocumentalResource\Pages;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Documentos\Enums\FamiliaDocumental;
use Modules\Documentos\Enums\OrigenEni;
use Modules\Documentos\Enums\PoliticaVersiones;
use Modules\Documentos\Models\TipoDocumental;

/**
 * Backoffice: gestión de tipos documentales (custodia v2).
 *
 * Solo adm_sistema. Código, familia y origen ENI se bloquean en cuanto el tipo tiene
 * documentos, y un tipo con documentos no se borra: se desactiva. El modelo aplica
 * las mismas reglas aunque se salte el formulario.
 */
class TipoDocumentalResource extends Resource
{
    protected static ?string $model = TipoDocumental::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder-open';

    protected static ?string $navigationLabel = 'Tipos documentales';

    protected static string|\UnitEnum|null $navigationGroup = 'Informes y Plantillas';

    protected static ?string $modelLabel = 'Tipo documental';

    protected static ?string $pluralModelLabel = 'Tipos documentales';

    protected static ?int $navigationSort = 5;

    /**
     * Define el formulario de tipos documentales.
     *
     * @param Schema $schema Esquema base del formulario.
     *
     * @return Schema
     */
    public static function form(Schema $schema): Schema
    {
        $bloqueadoConDocumentos = fn (?TipoDocumental $record): bool => $record !== null && $record->documentos()->exists();
        $mb = 1024 * 1024;

        return $schema->components([
            Section::make('Identificación')
                ->columns(2)
                ->schema([
                    TextInput::make('codigo')
                        ->label('Código')
                        ->required()
                        ->maxLength(100)
                        ->regex('/^[a-z][a-z0-9_]*$/')
                        ->unique(ignoreRecord: true)
                        ->disabled($bloqueadoConDocumentos)
                        ->helperText('Minúsculas, números y guiones bajos. No se puede cambiar cuando hay documentos del tipo.'),

                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(200),

                    Select::make('familia')
                        ->label('Familia')
                        ->options(self::opciones(FamiliaDocumental::cases()))
                        ->required()
                        ->disabled($bloqueadoConDocumentos),

                    Select::make('origen_eni')
                        ->label('Origen (ENI)')
                        ->options(self::opciones(OrigenEni::cases()))
                        ->required()
                        ->disabled($bloqueadoConDocumentos),

                    Toggle::make('activo')
                        ->label('Activo')
                        ->helperText('Un tipo inactivo no se ofrece en el alta de documentos; sus documentos siguen accesibles.')
                        ->default(true),
                ]),

            Section::make('Validez y conservación')
                ->columns(2)
                ->schema([
                    Toggle::make('caduca')
                        ->label('Caduca')
                        ->live(),

                    TextInput::make('validez_dias')
                        ->label('Validez por defecto (días)')
                        ->integer()
                        ->minValue(1)
                        ->visible(fn (Get $get): bool => (bool) $get('caduca'))
                        ->required(fn (Get $get): bool => (bool) $get('caduca')),

                    Select::make('politica_versiones')
                        ->label('Versiones anteriores')
                        ->options(self::opciones(PoliticaVersiones::cases()))
                        ->default(PoliticaVersiones::Conservar->value)
                        ->required(),

                    TextInput::make('conservacion_anyos')
                        ->label('Plazo de conservación (años)')
                        ->integer()
                        ->minValue(1)
                        ->helperText('Vacío: sin plazo definido, nunca se propone su destrucción.'),
                ]),

            Section::make('Reglas')
                ->columns(2)
                ->schema([
                    Toggle::make('visible_ciudadano_defecto')
                        ->label('Visible para el ciudadano por defecto'),

                    Toggle::make('requiere_firma')
                        ->label('Requiere firma'),

                    TextInput::make('max_bytes')
                        ->label('Tamaño máximo (MB)')
                        ->numeric()
                        ->minValue(1)
                        ->required()
                        ->default(fn (): int => intdiv((int) config('documentos.max_bytes_defecto'), $mb))
                        ->formatStateUsing(fn (?int $state): ?int => $state === null ? null : intdiv($state, $mb))
                        ->dehydrateStateUsing(fn ($state): int => (int) round(((float) $state) * $mb)),

                    TextInput::make('max_paginas')
                        ->label('Páginas máximas')
                        ->integer()
                        ->minValue(1)
                        ->required()
                        ->default(fn (): int => (int) config('documentos.max_paginas_defecto')),

                    TagsInput::make('metadatos_requeridos')
                        ->label('Metadatos obligatorios')
                        ->placeholder('fecha_emision, organo_emisor…')
                        ->default([]),

                    CheckboxList::make('vinculables')
                        ->label('Se puede vincular a')
                        ->options([
                            'ciudadano' => 'Persona',
                            'intervencion' => 'Plan de intervención',
                            'valoracion' => 'Valoración',
                        ])
                        ->default(['ciudadano'])
                        ->required(),
                ]),
        ]);
    }

    /**
     * Configura el listado de tipos documentales.
     *
     * @param Table $table Tabla base.
     *
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->label('Código')->searchable()->sortable()->fontFamily('mono'),
                TextColumn::make('nombre')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('familia')->label('Familia')->badge()
                    ->formatStateUsing(fn (FamiliaDocumental $state): string => $state->etiqueta()),
                IconColumn::make('caduca')->label('Caduca')->boolean(),
                TextColumn::make('documentos_count')->label('Documentos')->counts('documentos'),
                IconColumn::make('activo')->label('Activo')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('activo')->label('Activo'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (TipoDocumental $record): bool => ! $record->documentos()->exists()),
            ])
            ->defaultSort('nombre');
    }

    /**
     * Declara las páginas del recurso.
     *
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTiposDocumentales::route('/'),
            'create' => Pages\CreateTipoDocumental::route('/create'),
            'edit' => Pages\EditTipoDocumental::route('/{record}/edit'),
        ];
    }

    /**
     * Solo adm_sistema ve el recurso.
     *
     * @return bool
     */
    public static function canViewAny(): bool
    {
        return self::esAdmSistema();
    }

    /**
     * Solo adm_sistema crea tipos.
     *
     * @return bool
     */
    public static function canCreate(): bool
    {
        return self::esAdmSistema();
    }

    /**
     * Solo adm_sistema edita tipos.
     *
     * @param Model $record Registro objetivo.
     *
     * @return bool
     */
    public static function canEdit(Model $record): bool
    {
        return self::esAdmSistema();
    }

    /**
     * Solo adm_sistema borra, y solo tipos sin documentos.
     *
     * @param Model $record Registro objetivo.
     *
     * @return bool
     */
    public static function canDelete(Model $record): bool
    {
        return self::esAdmSistema() && $record instanceof TipoDocumental && ! $record->documentos()->exists();
    }

    /**
     * Indica si el usuario autenticado es adm_sistema.
     *
     * @return bool
     */
    private static function esAdmSistema(): bool
    {
        return auth()->user()?->hasRole('adm_sistema') ?? false;
    }

    /**
     * Opciones de un select a partir de los casos de un enum con etiqueta().
     *
     * @param array<FamiliaDocumental|OrigenEni|PoliticaVersiones> $casos Casos del enum.
     *
     * @return array<string, string>
     */
    private static function opciones(array $casos): array
    {
        return collect($casos)->mapWithKeys(fn ($caso): array => [$caso->value => $caso->etiqueta()])->all();
    }
}
