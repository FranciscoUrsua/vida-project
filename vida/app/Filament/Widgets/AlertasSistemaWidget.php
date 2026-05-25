<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Modules\Mensajes\Models\Alerta;
use Modules\Usuarios\Models\UsuarioRol;

/**
 * Widget de alertas activas del sistema dirigidas a administración.
 * Solo alertas de ámbito de backoffice (origen sistema/permisos).
 */
class AlertasSistemaWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 2;
    protected static ?string $heading = 'Alertas del sistema';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema', 'supervision']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Alerta::query()
                    ->whereIn('origen_type', [
                        'sistema',
                        UsuarioRol::class,
                    ])
                    ->pendientes()
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge(),
                Tables\Columns\TextColumn::make('mensaje')
                    ->label('Descripción')
                    ->limit(60),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Generada')
                    ->since(),
            ])
            ->emptyStateHeading('Sin alertas activas');
    }
}
