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

    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));
    }
}
