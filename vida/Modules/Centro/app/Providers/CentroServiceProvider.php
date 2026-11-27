<?php

namespace Modules\Centro\Providers;

use App\Events\DireccionCiudadanoNormalizada;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Centro\Console\AplicarDenominacionesCommand;
use Modules\Centro\Console\ComprobarCoberturaCommand;
use Modules\Centro\Listeners\AsignarCentroPorDireccion;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\InscripcionSesion;
use Modules\Centro\Models\Prescripcion;
use Modules\Centro\Models\TipoCentro;
use Modules\Centro\Policies\CentroPolicy;
use Modules\Centro\Policies\InscripcionSesionPolicy;
use Modules\Centro\Policies\PrescripcionPolicy;
use Modules\Centro\Policies\TipoCentroPolicy;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;

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
     * Registra los servicios del módulo en el contenedor: la configuración
     * efectiva de los centros, con ámbito de petición.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->scoped(ConfiguracionEfectivaCentro::class);
    }

    /**
     * Arranca los servicios del módulo: asignación de centro por domicilio y
     * comandos de cobertura y de denominaciones.
     *
     * @return void
     */
    public function boot(): void
    {
        Event::listen(DireccionCiudadanoNormalizada::class, AsignarCentroPorDireccion::class);

        Gate::policy(TipoCentro::class, TipoCentroPolicy::class);
        Gate::policy(Centro::class, CentroPolicy::class);
        Gate::policy(InscripcionSesion::class, InscripcionSesionPolicy::class);
        Gate::policy(Prescripcion::class, PrescripcionPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ComprobarCoberturaCommand::class, AplicarDenominacionesCommand::class]);
        }
    }
}
