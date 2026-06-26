<?php

namespace App\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Modules\Usuarios\Models\UsuarioRol;

/**
 * Widget de asignaciones de rol pendientes de aprobación.
 * Acceso directo a la gestión desde el dashboard.
 */
class RolesPendientesWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 2;

    protected static ?string $heading = 'Roles pendientes de aprobación';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema', 'adm_usuarios']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(UsuarioRol::pendientes()->with(['usuario', 'rol']))
            ->columns([
                Tables\Columns\TextColumn::make('usuario.name')
                    ->label('Profesional')
                    ->searchable(),
                Tables\Columns\TextColumn::make('rol.name')
                    ->label('Rol solicitado')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Solicitado')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Action::make('aprobar')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->authorize(fn () => auth()->user()?->hasAnyRole(['adm_sistema', 'adm_usuarios']) ?? false)
                    ->action(function (UsuarioRol $record): void {
                        $record->update(['estado' => 'activo']);
                    }),
            ])
            ->emptyStateHeading('No hay roles pendientes')
            ->emptyStateDescription('Todas las asignaciones están al día.');
    }
}
