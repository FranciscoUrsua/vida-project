<?php

namespace Modules\Usuarios\Providers;

use App\Models\Apunte;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Policies\CiudadanoPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Usuarios\Console\Commands\ReconciliarRoles;
use Modules\Usuarios\Policies\ApuntePolicy;
use Modules\Usuarios\Policies\HistoriaSocialPolicy;

/**
 * Provider del módulo Usuarios.
 *
 * Registra las Policies de autorización y carga las migraciones del módulo.
 */
class UsuariosServiceProvider extends ServiceProvider
{
    /**
     * Nombre del módulo.
     */
    protected string $moduleName = 'Usuarios';

    /**
     * Registra servicios en el contenedor.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Arranca los servicios del módulo y registra las Policies.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));

        $this->commands([
            ReconciliarRoles::class,
        ]);

        // Registrar Policies de autorización del módulo.
        // HistoriaSocial y Apunte son stubs temporales en App\Models hasta que
        // se implemente Modules\Intervencion. Las Policies viven aquí porque
        // implementan el modelo de permisos de rol definido en este módulo
        // (docs/modulo-usuarios-permisos.md). Cuando Intervencion exista,
        // mover las policies y este registro a IntevencionServiceProvider.
        Gate::policy(HistoriaSocial::class, HistoriaSocialPolicy::class);
        Gate::policy(Apunte::class, ApuntePolicy::class);
        Gate::policy(Ciudadano::class, CiudadanoPolicy::class);
    }
}
