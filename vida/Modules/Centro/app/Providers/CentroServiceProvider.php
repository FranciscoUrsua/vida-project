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
     */
    public function register(): void {}

    /**
     * Arranca los servicios del módulo.
     */
    public function boot(): void {}
}
