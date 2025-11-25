<?php

namespace Modules\Centro\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Modules\Centro\Providers\RouteServiceProvider as CentroRouteServiceProvider;

class CentroServiceProvider extends ServiceProvider
{
    /**
     * The module namespace.
     *
     * @var string
     */
    protected string $moduleName = 'Centro';

    /**
     * The module namespace.
     *
     * @var string
     */
    protected string $moduleNamespace = 'Modules\Centro';

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerConfig();
        $this->registerTranslations();
        $this->mergeConfigFrom(
            path: __DIR__ . '/../Config/config.php',
            key: $this->moduleName
        );
    }

    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerViews();
        $this->registerTranslations();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadSeedersFrom(__DIR__ . '/../Database/Seeders');

        // Registrar el RouteServiceProvider para rutas del módulo
        $this->app->register(CentroRouteServiceProvider::class);

        // Opcional: Publicar assets (e.g., migrations, views) para desarrollo
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../Config' => config_path($this->moduleName),
                __DIR__ . '/../Database/Migrations' => database_path('migrations'),
                __DIR__ . '/../Database/Seeders' => database_path('seeders'),
                __DIR__ . '/../Resources/views' => resource_path('views/' . $this->moduleName),
            ], [$this->moduleName . '-config', $this->moduleName . '-migrations', $this->moduleName . '-views']);
        }
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path($this->moduleName . '.php'),
        ], 'config');

        if (! config()->has($this->moduleName)) {
            $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', $this->moduleName);
        }
    }

    /**
     * Register views.
     *
     * @return void
     */
    protected function registerViews(): void
    {
        $viewPath = resource_path('views/modules/' . $this->moduleName);

        $sourcePath = __DIR__ . '/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
        ], ['views', $this->moduleName . '-views']);

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/' . $this->moduleName;
        }, \Config::get('view.paths')), [$sourcePath]), $this->moduleName);
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/' . $this->moduleName);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleName);
            $this->loadJsonTranslationsFrom($langPath, $this->moduleName);
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', $this->moduleName);
            $this->loadJsonTranslationsFrom(__DIR__ . '/../Resources/lang', $this->moduleName);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [];
    }
}
