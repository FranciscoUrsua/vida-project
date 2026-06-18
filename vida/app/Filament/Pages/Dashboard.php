<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ActividadCatalogosWidget;
use App\Filament\Widgets\AlertasSistemaWidget;
use App\Filament\Widgets\EstadoSistemaWidget;
use App\Filament\Widgets\RolesPendientesWidget;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Panel de inicio del backoffice de VIDA 360.
 *
 * Muestra indicadores de estado del sistema de configuración:
 * prestaciones activas, centros, profesionales y alertas operativas.
 * No muestra métricas de actividad asistencial (→ Power BI).
 * Ver principio 3.14 en docs/principios-vida360.md.
 */
class Dashboard extends BaseDashboard
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Panel principal';

    protected static ?string $title = 'Panel principal';

    protected static ?int $navigationSort = -10;

    /**
     * Devuelve el número de columnas del dashboard.
     *
     * @return int|array
     */
    public function getColumns(): int|array
    {
        return 4;
    }

    /**
     * Devuelve los widgets visibles en el dashboard.
     *
     * @return array
     */
    public function getWidgets(): array
    {
        return [
            EstadoSistemaWidget::class,
            RolesPendientesWidget::class,
            AlertasSistemaWidget::class,
            ActividadCatalogosWidget::class,
        ];
    }
}
