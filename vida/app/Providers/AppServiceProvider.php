<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $commonTraits = [
            \App\Common\Traits\Auditable::class,
            \App\Common\Traits\Versionable::class,
        ];

        // No necesitas registrar el observer; el trait lo maneja
        // Para inyección de servicio (opcional, si usas DI en controllers)
        $this->app->singleton(AuditService::class, function ($app) {
            return new AuditService();
        });
    }
}
