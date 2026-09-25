<?php

namespace Modules\Documentos\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Documentos\Console\LimpiarHuerfanosCommand;
use Modules\Documentos\Console\VerificarIntegridadCommand;
use Modules\Documentos\Contracts\AlmacenDocumentos;
use Modules\Documentos\Contracts\ProveedorClavesMaestras;
use Modules\Documentos\Models\EstiloInforme;
use Modules\Documentos\Observers\EstiloInformeObserver;
use Modules\Documentos\Services\Almacenamiento\AlmacenFlysystem;
use Modules\Documentos\Services\Almacenamiento\ProveedorClavesLocal;
use Modules\Documentos\Services\ResolverEstiloInforme;

/**
 * Provider del módulo Documentos.
 *
 * Registra migraciones, configuración, servicios y observers del módulo.
 */
class DocumentosServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Documentos';

    /**
     * Registra configuración y servicios del módulo.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/documentos.php',
            'documentos'
        );

        $this->app->singleton(ResolverEstiloInforme::class);

        $this->app->singleton(AlmacenDocumentos::class, AlmacenFlysystem::class);
        // Sin singleton: la clave maestra se valida en cada resolución, así una
        // configuración ausente falla en el primer uso y no queda cacheada.
        $this->app->bind(ProveedorClavesMaestras::class, ProveedorClavesLocal::class);
    }

    /**
     * Arranca el módulo Documentos: migraciones, observers, vistas, rutas y comandos.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));

        EstiloInforme::observe(EstiloInformeObserver::class);

        $this->loadViewsFrom(module_path($this->moduleName, 'resources/views'), 'documentos');

        $this->loadRoutesFrom(module_path($this->moduleName, 'routes/web.php'));

        if ($this->app->runningInConsole()) {
            $this->commands([
                VerificarIntegridadCommand::class,
                LimpiarHuerfanosCommand::class,
            ]);
        }
    }
}
