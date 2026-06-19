<?php

namespace Modules\Prestaciones\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Provider del módulo Prestaciones.
 *
 * Las migraciones del módulo residen en database/migrations/ (carpeta principal)
 * por convención del proyecto, por lo que no se cargan desde aquí.
 */
class PrestacionesServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Prestaciones';

    /**
     * No registra bindings adicionales en este módulo.
     */
    public function register(): void {}

    /**
     * No realiza arranque adicional en este módulo.
     */
    public function boot(): void {}
}
