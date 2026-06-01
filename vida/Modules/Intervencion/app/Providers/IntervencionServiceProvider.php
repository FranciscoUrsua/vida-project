<?php

namespace Modules\Intervencion\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Intervencion\Http\Livewire\AgendaPage;
use Modules\Intervencion\Http\Livewire\BuscarCiudadanoPage;
use Modules\Intervencion\Http\Livewire\MisCasosPage;
use Modules\Intervencion\Http\Livewire\Sidebar;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Policies\ApuntePolicy;
use Modules\Intervencion\Policies\PlanDeIntervencionPolicy;
use Modules\Intervencion\Services\IntervencionSidebarDataService;

/**
 * Provider del módulo Intervención.
 *
 * Registra migraciones, policies, vistas, rutas y componentes Livewire
 * del módulo de gestión del ciclo de intervención social.
 */
class IntervencionServiceProvider extends ServiceProvider
{
    /** @var string */
    protected string $moduleName = 'Intervencion';

    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(IntervencionSidebarDataService::class);
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));

        $this->loadViewsFrom(module_path($this->moduleName, 'resources/views'), 'intervencion');

        $this->loadRoutesFrom(module_path($this->moduleName, 'routes/web.php'));

        Gate::policy(Apunte::class, ApuntePolicy::class);
        Gate::policy(PlanDeIntervencion::class, PlanDeIntervencionPolicy::class);

        Livewire::component('intervencion.sidebar', Sidebar::class);
        Livewire::component('intervencion.agenda-page', AgendaPage::class);
        Livewire::component('intervencion.mis-casos-page', MisCasosPage::class);
        Livewire::component('intervencion.buscar-ciudadano-page', BuscarCiudadanoPage::class);
    }
}
