<?php

namespace Modules\Intervencion\Providers;

use App\Models\HistoriaSocial;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Intervencion\Http\Livewire\AgendaPage;
use Modules\Intervencion\Http\Livewire\BuscarCiudadanoPage;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use Modules\Intervencion\Http\Livewire\MisCasosPage;
use Modules\Intervencion\Http\Livewire\RegistrarEscalaPage;
use Modules\Intervencion\Http\Livewire\RegistrarValoracionPage;
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
    protected string $moduleName = 'Intervencion';

    /**
     * Registra los servicios singleton del módulo.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(IntervencionSidebarDataService::class);
    }

    /**
     * Carga la configuración, rutas, vistas y componentes del módulo.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));

        // El binding ignora el AmbitoUoScope para que la policy pueda emitir 403
        // en lugar de 404 cuando el usuario no tiene acceso al recurso.
        Route::bind('historia', fn ($value) => HistoriaSocial::withoutGlobalScopes()->findOrFail($value));

        $this->loadViewsFrom(module_path($this->moduleName, 'resources/views'), 'intervencion');

        $this->loadRoutesFrom(module_path($this->moduleName, 'routes/web.php'));

        Gate::policy(Apunte::class, ApuntePolicy::class);
        Gate::policy(PlanDeIntervencion::class, PlanDeIntervencionPolicy::class);

        Livewire::component('intervencion.sidebar', Sidebar::class);
        Livewire::component('intervencion.agenda-page', AgendaPage::class);
        Livewire::component('intervencion.mis-casos-page', MisCasosPage::class);
        Livewire::component('intervencion.buscar-ciudadano-page', BuscarCiudadanoPage::class);
        Livewire::component('intervencion.ciudadano-page', CiudadanoPage::class);
        Livewire::component('intervencion.registrar-valoracion-page', RegistrarValoracionPage::class);
        Livewire::component('intervencion.registrar-escala-page', RegistrarEscalaPage::class);
    }
}
