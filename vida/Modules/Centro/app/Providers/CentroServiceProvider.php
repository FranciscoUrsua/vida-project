<?php

namespace Modules\Centro\Providers;

use App\Events\DireccionCiudadanoNormalizada;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Centro\Console\ComprobarCoberturaCommand;
use Modules\Centro\Listeners\AsignarCentroPorDireccion;

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
    public function register(): void {}

    /**
     * Arranca los servicios del módulo: asignación de centro por domicilio y
     * comando de cobertura.
     *
     * @return void
     */
    public function boot(): void
    {
        Event::listen(DireccionCiudadanoNormalizada::class, AsignarCentroPorDireccion::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ComprobarCoberturaCommand::class]);
        }
    }
}
