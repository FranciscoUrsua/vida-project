<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Proveedor principal de servicios de la aplicación.
 *
 * Las Policies de autorización se registran en el módulo Usuarios
 * (Modules\Usuarios\Providers\UsuariosServiceProvider).
 * Este provider mantiene solo los registros globales de la aplicación.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra servicios en el contenedor de la aplicación.
     */
    public function register(): void
    {
        //
    }

    /**
     * Arranca los servicios de la aplicación.
     */
    public function boot(): void
    {
        //
    }
}
