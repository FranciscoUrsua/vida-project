<?php

namespace Modules\Intervencion\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Policies\ApuntePolicy;
use Modules\Intervencion\Policies\PlanDeIntervencionPolicy;

/**
 * Provider del módulo Intervención.
 *
 * Registra migraciones y policies del módulo de gestión del ciclo de intervención social.
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
        //
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));

        Gate::policy(Apunte::class, ApuntePolicy::class);
        Gate::policy(PlanDeIntervencion::class, PlanDeIntervencionPolicy::class);
    }
}
