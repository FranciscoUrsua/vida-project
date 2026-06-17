<?php

namespace Modules\Centro\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Provider del módulo Centro.
 *
 * Las migraciones del módulo residen en database/migrations/ (carpeta principal)
 * por convención del proyecto, por lo que no se cargan desde aquí.
 */
class CentroServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Centro';

    /**
     * Registra los servicios del módulo en el contenedor.
     *
     * @return void
     */
    public function register(): void
    {
    }

    /**
     * Arranca los servicios del módulo.
     *
     * @return void
     */
    public function boot(): void
    {
    }
}
