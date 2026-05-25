<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UsuarioRolResource\Pages;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Usuarios\Models\UsuarioRol;
use Spatie\Permission\Models\Role;
use App\Filament\Concerns\AutorizaGestion;

/**
 * Backoffice: supervisión del historial de asignaciones de rol.
 *
 * Muestra el historial completo de roles (pendientes, activos, inactivos)
 * y permite al supervisor aprobar asignaciones pendientes.
 *
 * Accesible en /admin/usuario-roles.
 */
class UsuarioRolResource extends Resource
{
    use AutorizaGestion;
    protected static ?string $model = UsuarioRol::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Historial de roles';
    protected static string|\UnitEnum|null $navigationGroup = 'Organización';
    protected static ?string $modelLabel = 'Asignación de rol';
    protected static ?string $pluralModelLabel = 'Historial de roles';
    protected static ?string $slug = 'usuario-roles';
    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Asignación de rol')
                ->schema([
                    Select::make('usuario_id')
                        ->label('Usuario')
                        ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),

                    Select::make('rol_id')
                        ->label('Rol')
                        ->options(fn () => Role::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),

                    Select::make('estado')
                        ->label('Estado')
                        ->options([
                            'pendiente_aprobacion' => 'Pendiente de aprobación',
                            'activo'               => 'Activo',
                            'inactivo'             => 'Inactivo',
                        ])
                        ->required(),

                    DatePicker::make('fecha_inicio')
                        ->label('Fecha de inicio')
                        ->required(),

                    DatePicker::make('fecha_fin')
                        ->label('Fecha de fin')
                        ->helperText('Dejar en blanco si no tiene fecha de expiración.'),

                    Select::make('asignado_por')
                        ->label('Asignado por')
                        ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                        ->searchable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();
                if ($user->hasRole('adm_sistema')) return;
                $uoIds = $user->uoSubtreeIds();
                if (empty($uoIds)) { $query->whereRaw('1 = 0'); return; }
                $query->whereIn('unidad_organizativa_id', $uoIds);
            })
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
                        'activo'               => 'success',
                        'pendiente_aprobacion' => 'warning',
                        'inactivo'             => 'gray',
                        default                => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'activo'               => 'Activo',
                        'pendiente_aprobacion' => 'Pendiente',
                        'inactivo'             => 'Inactivo',
                        default                => $state,
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
                        'activo'               => 'Activo',
                        'inactivo'             => 'Inactivo',
                    ]),
                Tables\Filters\SelectFilter::make('rol_id')
                    ->label('Rol')
                    ->relationship('rol', 'name'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsuarioRoles::route('/'),
            'create' => Pages\CreateUsuarioRol::route('/create'),
            'edit'   => Pages\EditUsuarioRol::route('/{record}/edit'),
        ];
    }

    /** adm_usuarios solo gestiona asignaciones de rol de su subtree de UO. */
    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        if (! $user?->hasAnyRole(['adm_sistema', 'adm_usuarios'])) return false;
        if ($user->hasRole('adm_sistema')) return true;
        return in_array($record->unidad_organizativa_id, $user->uoSubtreeIds());
    }
}
