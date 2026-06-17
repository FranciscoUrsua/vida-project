<?php

namespace Modules\Organizacion\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Organizacion\Services\ConfiguracionService;

/**
 * Provider del módulo Organizacion.
 *
 * Registra el ConfiguracionService como singleton para que
 * la caché sea compartida en toda la petición.
 */
class OrganizacionServiceProvider extends ServiceProvider
{
    /**
     * Nombre del módulo.
     */
    protected string $moduleName = 'Organizacion';

    /**
     * Registra los servicios del módulo en el contenedor.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(ConfiguracionService::class);
    }

    /**
     * Arranca los servicios del módulo.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));
    }
}
