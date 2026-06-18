<?php

namespace Modules\Mensajes\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Mensajes\Http\Livewire\BuzonPage;
use Modules\Mensajes\Jobs\EscalarAlertasVencidasJob;
use Modules\Mensajes\Livewire\BadgeNotificaciones;
use Modules\Mensajes\Livewire\BandejaAlertas;
use Modules\Mensajes\Livewire\BandejaMensajes;
use Modules\Mensajes\Livewire\HiloMensajes;
use Modules\Mensajes\Livewire\NuevoMensaje;
use Modules\Mensajes\Services\AlertaService;
use Modules\Mensajes\Services\HorarioLaboralService;
use Modules\Mensajes\Services\MensajeriaService;

/**
 * Provider del módulo Mensajes.
 *
 * Registra las migraciones, servicios, componentes Livewire y el
 * scheduler del job de escalada de alertas vencidas.
 */
class MensajesServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Mensajes';

    /**
     * Registra los servicios singleton del módulo.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(HorarioLaboralService::class);
        $this->app->singleton(AlertaService::class);
        $this->app->singleton(MensajeriaService::class);
    }

    /**
     * Carga migraciones, vistas, componentes y scheduler del módulo.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));

        $this->loadViewsFrom(module_path($this->moduleName, 'resources/views'), 'mensajes');

        // Registrar componentes Livewire del módulo
        Livewire::component('mensajes.buzon-page', BuzonPage::class);
        Livewire::component('mensajes-badge-notificaciones', BadgeNotificaciones::class);
        Livewire::component('mensajes-bandeja-alertas', BandejaAlertas::class);
        Livewire::component('mensajes-bandeja-mensajes', BandejaMensajes::class);
        Livewire::component('mensajes-hilo-mensajes', HiloMensajes::class);
        Livewire::component('mensajes-nuevo-mensaje', NuevoMensaje::class);

        // Registrar el job de escalada en el scheduler
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->job(EscalarAlertasVencidasJob::class)->everyFifteenMinutes();
        });
    }
}
