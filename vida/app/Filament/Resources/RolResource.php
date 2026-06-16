<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RolResource\Pages;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

/**
 * Resource Filament para gestionar Roles y su matriz de permisos.
 *
 * Permite visualizar y modificar qué permisos atómicos tiene cada rol.
 * Solo el rol adm_sistema debe tener acceso a este recurso (sección 4.5).
 *
 * Accesible en /admin/rols.
 */
class RolResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Roles y permisos';

    protected static string|\UnitEnum|null $navigationGroup = 'Organización';

    protected static ?string $modelLabel = 'Rol';

    protected static ?string $pluralModelLabel = 'Roles';

    protected static ?int $navigationSort = 70;

    /**
     * Configura el formulario de roles.
     *
     * @param Schema $schema Schema Filament a configurar.
     * @return Schema Schema configurado.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Datos del rol')
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre del rol')
                        ->required()
                        ->maxLength(255),
                ]),

            Section::make('Permisos asignados')
                ->description('Selecciona los permisos atómicos que tendrá este rol. Modificar permisos afecta a todos los usuarios con este rol.')
                ->schema([
                    CheckboxList::make('permissions')
                        ->label('Permisos')
                        ->relationship('permissions', 'name')
                        ->columns(3)
                        ->searchable(),
                ]),
        ]);
    }

    /**
     * Configura la tabla de roles.
     *
     * @param Table $table Tabla Filament a configurar.
     * @return Table Tabla configurada.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('Permisos')
                    ->counts('permissions')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->defaultSort('name');
    }

    /**
     * Devuelve las paginas registradas para el recurso.
     *
     * @return array<string, mixed> Rutas de paginas Filament.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRol::route('/create'),
            'edit' => Pages\EditRol::route('/{record}/edit'),
        ];
    }

    /**
     * Determina si el usuario autenticado puede ver roles y permisos.
     *
     * @return bool True si puede ver roles y permisos.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('adm_sistema') ?? false;
    }

    /**
     * Determina si el usuario autenticado puede crear roles.
     *
     * @return bool True si puede crear roles.
     */
    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('adm_sistema') ?? false;
    }

    /**
     * Determina si el usuario autenticado puede editar el rol.
     *
     * @param Model $record Rol evaluado.
     * @return bool True si puede editar el rol.
     */
    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasRole('adm_sistema') ?? false;
    }

    /**
     * Determina si el usuario autenticado puede eliminar el rol.
     *
     * @param Model $record Rol evaluado.
     * @return bool True si puede eliminar el rol.
     */
    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole('adm_sistema') ?? false;
    }
}
