<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\SocialUser;

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
        // No necesitas registrar el observer; el trait lo maneja
        // Para inyección de servicio (opcional, si usas DI en controllers)
        $this->app->singleton(AuditService::class, function ($app) {
            return new AuditService();
        });
    }
}
