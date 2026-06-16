<?php

namespace App\Providers;

use App\Models\Ciudadano;
use App\Observers\DireccionObserver;
use App\Services\Geocodificacion\GeocodificadorInterface;
use App\Services\Geocodificacion\GeocodificadorService;
use Illuminate\Support\ServiceProvider;
use Modules\Centro\Models\Centro;

/**
 * Proveedor de servicios de geocodificación.
 *
 * Registra el binding de GeocodificadorInterface en el contenedor y
 * conecta el DireccionObserver a los modelos que usan TieneDireccion.
 *
 * Añadir aquí cualquier modelo futuro que incorpore TieneDireccion.
 *
 * Ver docs/geocodificacion.md § 2 y docs/decisiones-tecnicas.md § 9.
 */
class GeocodificacionServiceProvider extends ServiceProvider
{
    /**
     * Registra el binding de la interfaz en el contenedor.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(GeocodificadorInterface::class, GeocodificadorService::class);
    }

    /**
     * Registra el observer en los modelos con dirección.
     *
     * @return void
     */
    public function boot(): void
    {
        Ciudadano::observe(DireccionObserver::class);
        Centro::observe(DireccionObserver::class);
    }
}
