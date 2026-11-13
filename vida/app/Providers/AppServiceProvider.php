<?php

namespace App\Providers;

use App\Models\UnidadOrganizativa;
use App\Models\UsuarioUo;
use App\Services\AmbitoUsuario;
use Illuminate\Support\ServiceProvider;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\TipoCentro;

/**
 * Proveedor de servicios de la aplicación: registra el cálculo del ámbito de
 * los usuarios y su invalidación.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra los servicios de la aplicación.
     */
    public function register(): void
    {
        $this->app->scoped(AmbitoUsuario::class);
    }

    /**
     * Arranca los servicios de la aplicación.
     */
    public function boot(): void
    {
        // El ámbito se cachea por petición: cualquier cambio de adscripciones, organigrama,
        // centros o UO funcional de un tipo lo invalida para que no quede desfasado (RN-13)
        $olvidar = fn () => $this->app->make(AmbitoUsuario::class)->olvidar();
        foreach ([UsuarioUo::class, UnidadOrganizativa::class, Centro::class, TipoCentro::class] as $modelo) {
            $modelo::saved($olvidar);
            $modelo::deleted($olvidar);
        }
    }
}
