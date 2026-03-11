<?php

namespace App\Providers;

use App\Models\Apunte;
use App\Models\HistoriaSocial;
use App\Policies\ApuntePolicy;
use App\Policies\HistoriaSocialPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Proveedor principal de servicios de la aplicación.
 *
 * Registra las Policies de autorización que implementan el modelo
 * de acceso de VIDA 360 (docs/modulo-usuarios-permisos.md § 4.4).
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra servicios en el contenedor de la aplicación.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Arranca los servicios de la aplicación y registra las Policies.
     *
     * @return void
     */
    public function boot(): void
    {
        Gate::policy(HistoriaSocial::class, HistoriaSocialPolicy::class);
        Gate::policy(Apunte::class, ApuntePolicy::class);
    }
}
