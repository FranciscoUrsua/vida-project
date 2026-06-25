<?php

namespace Modules\Supervision\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Supervision\Http\Livewire\ActividadesPage;
use Modules\Supervision\Http\Livewire\AprobacionesPage;
use Modules\Supervision\Http\Livewire\ConfiguracionCentroPage;
use Modules\Supervision\Http\Livewire\CuadrantePage;
use Modules\Supervision\Http\Livewire\EquipoPage;
use Modules\Supervision\Http\Livewire\AuditoriaPage;
use Modules\Supervision\Http\Livewire\InicioPage;
use Modules\Supervision\Http\Livewire\PlazasPage;
use Modules\Supervision\Http\Livewire\Sidebar;
use Modules\Supervision\Services\IndicadoresCentroService;
use Modules\Supervision\Services\SupervisionSidebarDataService;

/**
 * Provider del módulo Supervisión.
 *
 * Registra vistas, rutas, servicios y componentes Livewire del módulo
 * de gestión operativa del centro (equipo, agenda, aprobaciones, auditoría).
 */
class SupervisionServiceProvider extends ServiceProvider
{
    /** @var string */
    protected string $moduleName = 'Supervision';

    /**
     * Registra los servicios singleton del módulo.
     */
    public function register(): void
    {
        $this->app->singleton(SupervisionSidebarDataService::class);
        $this->app->singleton(IndicadoresCentroService::class);
    }

    /**
     * Carga configuración, rutas, vistas y componentes del módulo.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));

        $this->loadViewsFrom(module_path($this->moduleName, 'resources/views'), 'supervision');

        $this->loadRoutesFrom(module_path($this->moduleName, 'routes/web.php'));

        Livewire::component('supervision.sidebar',                  Sidebar::class);
        Livewire::component('supervision.inicio-page',              InicioPage::class);
        Livewire::component('supervision.cuadrante-page',           CuadrantePage::class);
        Livewire::component('supervision.actividades-page',         ActividadesPage::class);
        Livewire::component('supervision.plazas-page',              PlazasPage::class);
        Livewire::component('supervision.equipo-page',              EquipoPage::class);
        Livewire::component('supervision.auditoria-page',           AuditoriaPage::class);
        Livewire::component('supervision.aprobaciones-page',        AprobacionesPage::class);
        Livewire::component('supervision.configuracion-centro-page',ConfiguracionCentroPage::class);
    }
}
