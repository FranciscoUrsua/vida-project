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
    protected int | string | array $columnSpan = 2;
    protected static ?string $heading = 'Roles pendientes de aprobación';

    public function table(Table $table): Table
    {
        return $table
            ->query(UsuarioRol::pendientes()->with(['user', 'role']))
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Profesional')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role.name')
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
                    ->action(fn ($record) => $record->aprobar(auth()->user())),
            ])
            ->emptyStateHeading('No hay roles pendientes')
            ->emptyStateDescription('Todas las asignaciones están al día.');
    }
}
