<?php

namespace Modules\Ciudadania\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Ciudadania\Contracts\FuenteIdentidadInterface;
use Modules\Ciudadania\Http\Livewire\AltaCiudadano;
use Modules\Ciudadania\Services\MotorMatching;
use Modules\Ciudadania\Services\Padron\MockFuenteIdentidad;

/**
 * Provider del módulo Ciudadanía.
 *
 * Registra migraciones, vistas, rutas, servicios y componentes Livewire
 * del módulo de alta y gestión del expediente ciudadano.
 *
 * FuenteIdentidadInterface se enlaza a MockFuenteIdentidad por defecto
 * (principio 3.6: mock activo en todos los entornos no productivos).
 */
class CiudadaniaServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Ciudadania';

    public function register(): void
    {
        $this->app->bind(FuenteIdentidadInterface::class, MockFuenteIdentidad::class);
        $this->app->singleton(MotorMatching::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));

        $this->loadViewsFrom(module_path($this->moduleName, 'resources/views'), 'ciudadania');

        $this->loadRoutesFrom(module_path($this->moduleName, 'routes/web.php'));

        Livewire::component('ciudadania.alta-ciudadano', AltaCiudadano::class);
    }
}
