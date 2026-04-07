<?php

namespace Modules\Agenda\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Provider del módulo Agenda.
 *
 * Registra las migraciones y los servicios del módulo de citas y agendas.
 */
class AgendaServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Agenda';

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));
    }
}
