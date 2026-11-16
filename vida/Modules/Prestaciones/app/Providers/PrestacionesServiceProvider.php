<?php

namespace Modules\Prestaciones\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Centro\Services\PrescripcionService;
use Modules\Documentos\Policies\DocumentoPolicy;
use Modules\Prestaciones\Console\ConsultarExpedientes;
use Modules\Prestaciones\Http\Livewire\BandejaTramitacionPage;
use Modules\Prestaciones\Http\Livewire\PrestacionesPersona;
use Modules\Prestaciones\Http\Livewire\SolicitarPrestacion;
use Modules\Prestaciones\Http\Livewire\SolicitudPrestacionPage;
use Modules\Prestaciones\Http\Livewire\TramitacionSidebar;
use Modules\Prestaciones\Models\PrestacionPersona;
use Modules\Prestaciones\Models\SolicitudPrestacion;
use Modules\Prestaciones\Policies\PrestacionPersonaPolicy;
use Modules\Prestaciones\Policies\SolicitudPrestacionPolicy;
use Modules\Prestaciones\Services\AccesoDocumentosTramitacion;
use Modules\Prestaciones\Services\Gestor\AdaptadorGestorExpedientes;
use Modules\Prestaciones\Services\Gestor\GestorManual;
use Modules\Prestaciones\Services\Gestor\GestorSimulado;
use Modules\Prestaciones\Services\PrestacionPersonaService;

/**
 * Provider del módulo Prestaciones: catálogo, solicitudes de prestación y prestaciones de la persona.
 *
 * Las migraciones del módulo residen en database/migrations/ (carpeta principal) por
 * convención del proyecto. Liga el adaptador del gestor de expedientes según
 * `config('prestaciones.gestor_expedientes')` (manual por defecto) y engancha la asignación
 * de plazas para crear la prestación de la persona.
 */
class PrestacionesServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Prestaciones';

    /**
     * Liga el adaptador del gestor de expedientes.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(AdaptadorGestorExpedientes::class, fn () => match (config('prestaciones.gestor_expedientes')) {
            'simulado' => new GestorSimulado,
            default => new GestorManual,
        });

        // Tramitación ve los documentos de las personas cuyas solicitudes tramita (fase 4)
        $this->app->tag([AccesoDocumentosTramitacion::class], DocumentoPolicy::ETIQUETA_ACCESOS);

        // Al asignar una plaza a una prescripción nacida de una prestación, prestación de la persona activa
        $this->app->afterResolving(PrescripcionService::class, function (PrescripcionService $prescripciones): void {
            $prescripciones->setAlAsignarPlaza(fn ($prescripcion) => app(PrestacionPersonaService::class)->desdePrescripcion($prescripcion));
        });
    }

    /**
     * Registra policies, rutas, vistas, componentes y el comando del módulo.
     *
     * @return void
     */
    public function boot(): void
    {
        Gate::policy(SolicitudPrestacion::class, SolicitudPrestacionPolicy::class);
        Gate::policy(PrestacionPersona::class, PrestacionPersonaPolicy::class);

        Route::bind('solicitud', fn ($value) => SolicitudPrestacion::findOrFail($value));

        $this->loadViewsFrom(module_path($this->moduleName, 'resources/views'), 'prestaciones');
        $this->loadRoutesFrom(module_path($this->moduleName, 'routes/web.php'));

        Livewire::component('prestaciones.solicitud-page', SolicitudPrestacionPage::class);
        Livewire::component('prestaciones.bandeja-tramitacion', BandejaTramitacionPage::class);
        Livewire::component('prestaciones.solicitar-prestacion', SolicitarPrestacion::class);
        Livewire::component('prestaciones.prestaciones-persona', PrestacionesPersona::class);
        Livewire::component('prestaciones.tramitacion-sidebar', TramitacionSidebar::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ConsultarExpedientes::class]);
        }
    }
}
