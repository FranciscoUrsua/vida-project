<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Resources\UsuarioRolResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Usuarios\Models\UsuarioRol;

/**
 * Backoffice: consulta del historial de asignaciones de rol.
 *
 * Es de solo lectura. Los roles se asignan y retiran desde el formulario de
 * usuarios (AsignacionRolesService, con aprobación previa o alerta según 2.8) y
 * las solicitudes pendientes se resuelven en Supervisión → Aprobaciones. Crear
 * o editar filas aquí permitiría activar roles saltándose esa supervisión,
 * también sobre uno mismo, y reescribir el historial (principio 4.2).
 *
 * Accesible en /admin/usuario-roles.
 */
class UsuarioRolResource extends Resource
{
    use AutorizaGestion;

    protected static ?string $model = UsuarioRol::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Historial de roles';

    protected static string|\UnitEnum|null $navigationGroup = 'Usuarios y Profesionales';

    protected static ?string $modelLabel = 'Asignación de rol';

    protected static ?string $pluralModelLabel = 'Historial de roles';

    protected static ?string $slug = 'usuario-roles';

    protected static ?int $navigationSort = 4;

    /**
     * Configura el listado de asignaciones de rol.
     *
     * @param Table $table Tabla base.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('usuario.name')
                    ->label('Usuario')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('rol.name')
                    ->label('Rol')
                    ->sortable(),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'activo' => 'success',
                        'pendiente_aprobacion' => 'warning',
                        'inactivo' => 'gray',
                        'denegado' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'activo' => 'Activo',
                        'pendiente_aprobacion' => 'Pendiente',
                        'inactivo' => 'Inactivo',
                        'denegado' => 'Denegado',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('fecha_inicio')
                    ->label('Desde')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('fecha_fin')
                    ->label('Hasta')
                    ->date('d/m/Y')
                    ->placeholder('Sin expiración'),

                Tables\Columns\TextColumn::make('asignadoPor.name')
                    ->label('Asignado por')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'pendiente_aprobacion' => 'Pendiente de aprobación',
                        'activo' => 'Activo',
                        'inactivo' => 'Inactivo',
                        'denegado' => 'Denegado',
                    ]),
                Tables\Filters\SelectFilter::make('rol_id')
                    ->label('Rol')
                    ->relationship('rol', 'name'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Declara las páginas del catálogo de asignaciones de rol.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsuarioRoles::route('/'),
        ];
    }

    /**
     * El historial no admite altas manuales: se asigna desde el formulario de usuarios.
     *
     * @return bool
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * El historial no se edita: el pasado es inmutable y las aprobaciones van por Supervisión.
     *
     * @param Model $record Registro objetivo.
     *
     * @return bool
     */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /**
     * El historial no se borra.
     *
     * @param Model $record Registro objetivo.
     *
     * @return bool
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
