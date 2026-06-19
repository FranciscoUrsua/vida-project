<?php

namespace Modules\Atencion\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Atencion\Models\RegistroAtencion;
use Modules\Atencion\Policies\RegistroAtencionPolicy;

/**
 * Provider del módulo Atención.
 *
 * Registra migraciones y policy del modelo RegistroAtencion.
 */
class AtencionServiceProvider extends ServiceProvider
{
    /** @var string Nombre del módulo nwidart */
    protected string $moduleName = 'Atencion';

    /**
     * Registra dependencias del módulo.
     */
    public function register(): void {}

    /**
     * Carga migraciones y registra policies del módulo.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));

        Gate::policy(RegistroAtencion::class, RegistroAtencionPolicy::class);
    }
}
