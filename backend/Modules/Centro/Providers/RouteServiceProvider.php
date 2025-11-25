<?php

namespace Modules\Centro\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The module namespace to assume when generating URLs to actions.
     *
     * @var string
     */
    protected string $moduleNamespace = 'Modules\Centro\Http\Controllers';

    /**
     * The path to the cached routes directory.
     *
     * @var string
     */
    protected string $routesCachePath = __DIR__ . '/../Routes/cache';

    /**
     * Called before routes are registered.
     *
     * Register any model bindings or pattern based filters.
     *
     * @return void
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes(): void
    {
        Route::middleware('web')
            ->prefix('centro') // Prefix del módulo, ajusta si quieres /centro/...
            ->namespace($this->moduleNamespace)
            ->group(base_path('Modules/Centro/Routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes(): void
    {
        Route::prefix('api/centro') // Prefix para API del módulo, e.g., /api/centro/tipos-centros
            ->middleware('api') // Middleware API global (incluye Sanctum si configurado)
            ->namespace($this->moduleNamespace)
            ->group(base_path('Modules/Centro/Routes/api.php'));
    }

    /**
     * Define $apiRoutes para compatibilidad o uso manual (si lo necesitas en boot o elsewhere).
     *
     * Ejemplo: Cargar rutas API manualmente si no usas el map().
     *
     * @return void
     */
    protected function loadApiRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            $this->loadCachedApiRoutes();
        } else {
            $this->loadApiRoutesFromFile();
        }
    }

    /**
     * Carga rutas API desde archivo (api.php).
     *
     * @return void
     */
    protected function loadApiRoutesFromFile(): void
    {
        Route::prefix('api/centro')
            ->middleware('api')
            ->namespace($this->moduleNamespace)
            ->group(base_path('Modules/Centro/Routes/api.php'));
    }

    /**
     * Carga rutas API cacheadas.
     *
     * @return void
     */
    protected function loadCachedApiRoutes(): void
    {
        $routesPath = $this->routesCachePath . '/api.php';
        if (is_file($routesPath)) {
            Route::prefix('api/centro')
                ->middleware('api')
                ->namespace($this->moduleNamespace)
                ->group($routesPath);
        }
    }
}
