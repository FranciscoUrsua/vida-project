<?php

namespace Modules\Escalas\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Provider del módulo Escalas.
 *
 * Registra las migraciones del módulo.
 */
class EscalasServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Escalas';

    /**
     * Registra los servicios del módulo.
     *
     * @return void
     */
    public function register(): void {}

    /**
     * Arranca el módulo Escalas.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));
    }
}
