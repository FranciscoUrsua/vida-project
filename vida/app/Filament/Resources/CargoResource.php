<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\CargoResource\Pages;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Usuarios\Models\Cargo;
use Spatie\Permission\Models\Role;

/**
 * Backoffice: gestión del catálogo de cargos profesionales y de sus roles sugeridos.
 *
 * Los roles sugeridos solo pre-rellenan el alta de usuario (sección 2.9 de
 * docs/modulo-usuarios-permisos.md); cambiarlos no altera a ningún usuario.
 *
 * Accesible en /admin/cargos.
 */
class CargoResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = Cargo::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'Cargos';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?string $modelLabel = 'Cargo';

    protected static ?string $pluralModelLabel = 'Cargos';

    protected static ?int $navigationSort = 6;

    /**
     * Define el formulario de alta y edición de cargos.
     *
     * @param Schema $schema Esquema base.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos del cargo')
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre del cargo')
                        ->required()
                        ->maxLength(150),

                    TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(100)
                        ->helperText('Identificador estable en minúsculas con guiones. Ej: trabajador-social'),

                    Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(2)
                        ->nullable(),

                    Toggle::make('activo')
                        ->label('Activo')
                        ->default(true),
                ]),

            Section::make('Roles sugeridos')
                ->schema([
                    // Se guarda fila a fila en cargo_roles_sugeridos (auditado) mediante
                    // Cargo::sincronizarRolesSugeridos(); no es un atributo de cargos.
                    Select::make('roles_sugeridos')
                        ->label('Roles sugeridos')
                        ->multiple()
                        ->options(fn () => Role::orderBy('name')->pluck('name', 'name'))
                        ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                            $inexistentes = array_diff((array) $value, Role::pluck('name')->all());

                            if ($inexistentes !== []) {
                                $fail('No existe el rol: '.implode(', ', $inexistentes).'.');
                            }
                        })
                        ->helperText('Se proponen al dar de alta a un usuario con este cargo. No otorgan permisos por sí mismos.')
                        ->loadStateFromRelationshipsUsing(function (Select $component, ?Cargo $record): void {
                            $component->state($record?->rolesSugeridos()->orderBy('rol')->pluck('rol')->all() ?? []);
                        })
                        ->saveRelationshipsUsing(function (Cargo $record, ?array $state): void {
                            $record->sincronizarRolesSugeridos(array_values($state ?? []));
                        })
                        ->dehydrated(false),
                ]),
        ]);
    }

    /**
     * Define la tabla de listado de cargos.
     *
     * @param Table $table Tabla base.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Cargo')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('profesionales_count')
                    ->label('Profesionales')
                    ->counts('profesionales')
                    ->alignCenter(),

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
     * Define las páginas del recurso de cargos.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCargos::route('/'),
            'create' => Pages\CreateCargo::route('/create'),
            'edit' => Pages\EditCargo::route('/{record}/edit'),
        ];
    }
}
