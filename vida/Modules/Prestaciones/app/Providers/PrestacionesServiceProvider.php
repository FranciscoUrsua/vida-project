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

    public function register(): void {}

    public function boot(): void {}
}
