<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\Red;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\UsuarioRol;

/**
 * Widget de estado del sistema de configuración.
 * Solo contadores de entidades de configuración — no métricas asistenciales.
 */
class EstadoSistemaWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        return [
            Stat::make('Prestaciones activas', Prestacion::activas()->count())
                ->description('de ' . Prestacion::count() . ' en catálogo')
                ->icon('heroicon-o-list-bullet'),

            Stat::make('Centros', Centro::count())
                ->description('en ' . Red::count() . ' redes')
                ->icon('heroicon-o-building-library'),

            Stat::make('Profesionales activos', Profesional::activos()->count())
                ->icon('heroicon-o-users'),

            Stat::make('Roles pendientes', UsuarioRol::pendientes()->count())
                ->description('requieren aprobación')
                ->icon('heroicon-o-shield-exclamation')
                ->color('warning'),
        ];
    }
}
