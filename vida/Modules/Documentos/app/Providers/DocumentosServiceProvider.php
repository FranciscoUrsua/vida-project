<?php

namespace Modules\Documentos\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Documentos\Models\EstiloInforme;
use Modules\Documentos\Observers\EstiloInformeObserver;
use Modules\Documentos\Services\ResolverEstiloInforme;

/**
 * Provider del módulo Documentos.
 *
 * Registra migraciones, configuración, servicios y observers del módulo.
 */
class DocumentosServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Documentos';

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/documentos.php',
            'documentos'
        );

        $this->app->singleton(ResolverEstiloInforme::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));

        EstiloInforme::observe(EstiloInformeObserver::class);

        $this->loadViewsFrom(module_path($this->moduleName, 'resources/views'), 'documentos');

        $this->loadRoutesFrom(module_path($this->moduleName, 'routes/web.php'));
    }
}
