<?php

namespace App\Providers;

use App\Models\UnidadOrganizativa;
use App\Models\UsuarioUo;
use App\Services\AmbitoUsuario;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\TipoCentro;

/**
 * Proveedor de servicios de la aplicación: registra el cálculo del ámbito de
 * los usuarios y su invalidación, y el proveedor de autenticación que deja
 * fuera a los usuarios desactivados (RN-20).
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra los servicios de la aplicación.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->scoped(AmbitoUsuario::class);
    }

    /**
     * Arranca los servicios de la aplicación.
     *
     * @return void
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

        // RN-20: un usuario desactivado no inicia sesión ni conserva la que tenía abierta.
        // Al filtrar en el proveedor vale para el login, la sesión, «recordarme» y Filament.
        Auth::provider('eloquent_activos', fn (Application $app, array $config) => (new EloquentUserProvider($app['hash'], $config['model']))
            ->withQuery(fn ($query) => $query->whereNull('desactivado_en')));
    }
}
