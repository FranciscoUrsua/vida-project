<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RolResource\Pages;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Usuarios\Models\ConfiguracionRol;
use Spatie\Permission\Models\Role;

/**
 * Backoffice «Roles y permisos»: en una sola pantalla, los permisos atómicos de
 * cada rol y el nivel de supervisión que exige su asignación (§2.8).
 *
 * El nivel vive en `configuracion_roles` (los roles de Spatie no se tocan): las
 * páginas de creación y edición lo cargan y lo guardan con ConfiguracionRol.
 * Solo adm_sistema: adm_usuarios no configura roles (§2.3) y no debe poder
 * relajar la supervisión de las asignaciones que él mismo hace.
 *
 * Accesible en /admin/rols.
 *
 * @see docs/modulo-usuarios-permisos.md secciones 2.8 y 4.5
 */
class RolResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Roles y permisos';

    protected static string|\UnitEnum|null $navigationGroup = 'Usuarios y Profesionales';

    protected static ?string $modelLabel = 'Rol';

    protected static ?string $pluralModelLabel = 'Roles';

    protected static ?int $navigationSort = 3;

    /**
     * Define el formulario de roles.
     *
     * @param Schema $schema Esquema base del formulario.
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

            Section::make('Supervisión de la asignación')
                ->description('Qué control exige asignar este rol a un usuario.')
                ->schema([
                    Select::make('nivel_supervision')
                        ->label('Nivel de supervisión')
                        ->options([
                            ConfiguracionRol::APROBACION_PREVIA => 'Aprobación previa — la asignación no es efectiva hasta que el supervisor la aprueba',
                            ConfiguracionRol::ALERTA_SUPERVISADA => 'Alerta supervisada — efectiva inmediatamente, el supervisor recibe una alerta',
                        ])
                        ->required()
                        ->default(ConfiguracionRol::ALERTA_SUPERVISADA),
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
     * Configura el listado de roles.
     *
     * @param Table $table Tabla base.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('nivel_supervision')
                    ->label('Supervisión de la asignación')
                    ->badge()
                    ->getStateUsing(fn (Role $record): string => ConfiguracionRol::nivelPara($record))
                    ->formatStateUsing(fn (string $state): string => ConfiguracionRol::ETIQUETAS[$state] ?? $state)
                    ->color(fn (string $state): string => $state === ConfiguracionRol::APROBACION_PREVIA ? 'danger' : 'warning'),

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
     * Declara las páginas del recurso de roles.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRol::route('/create'),
            'edit' => Pages\EditRol::route('/{record}/edit'),
        ];
    }

    // Solo adm_sistema puede ver y modificar la matriz de roles y permisos.
    /**
     * Determina si el usuario puede ver el listado de roles.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('adm_sistema') ?? false;
    }

    /**
     * Determina si el usuario puede crear roles.
     */
    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('adm_sistema') ?? false;
    }

    /**
     * Determina si el usuario puede editar roles.
     *
     * @param Model $record Registro objetivo.
     */
    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasRole('adm_sistema') ?? false;
    }

    /**
     * Determina si el usuario puede eliminar roles.
     *
     * @param Model $record Registro objetivo.
     */
    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole('adm_sistema') ?? false;
    }
}
