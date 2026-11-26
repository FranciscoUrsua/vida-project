<?php

namespace Modules\Intervencion\Providers;

use App\Models\HistoriaSocial;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Centro\Services\Actividades\CicloVidaActividadService;
use Modules\Centro\Services\PrescripcionService;
use Modules\Documentos\Policies\DocumentoPolicy;
use Modules\Escalas\Models\PaseEscala;
use Modules\Intervencion\Http\Livewire\AgendaPage;
use Modules\Intervencion\Http\Livewire\AsignarPlazaModal;
use Modules\Intervencion\Http\Livewire\BuscarCiudadanoPage;
use Modules\Intervencion\Http\Livewire\CambioReferencia;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use Modules\Intervencion\Http\Livewire\DerivacionesPlan;
use Modules\Intervencion\Http\Livewire\EncargoPage;
use Modules\Intervencion\Http\Livewire\EncargosProfesionales;
use Modules\Intervencion\Http\Livewire\MisCasosPage;
use Modules\Intervencion\Http\Livewire\MisEncargosPage;
use Modules\Intervencion\Http\Livewire\PrescribirRecursoModal;
use Modules\Intervencion\Http\Livewire\RecursosPage;
use Modules\Intervencion\Http\Livewire\RegistrarEscalaPage;
use Modules\Intervencion\Http\Livewire\RegistrarValoracionPage;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Policies\ApuntePolicy;
use Modules\Intervencion\Policies\FichaPolicy;
use Modules\Intervencion\Policies\PaseEscalaPolicy;
use Modules\Intervencion\Policies\PlanDeIntervencionPolicy;
use Modules\Intervencion\Services\AccesoDocumentosEncargo;
use Modules\Intervencion\Services\AvisoActividadesService;
use Modules\Intervencion\Services\AvisoPlazasService;
use Modules\Intervencion\Services\IntervencionSidebarDataService;
use Random\Engine\Secure;
use Random\Randomizer;

/**
 * Provider del módulo Intervención.
 *
 * Registra migraciones, policies, vistas, rutas y componentes Livewire
 * del módulo de gestión del ciclo de intervención social.
 */
class IntervencionServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Intervencion';

    /**
     * Registra los servicios singleton del módulo.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(IntervencionSidebarDataService::class);

        // Azar del sorteo de referencias: seguro en producción; los tests fijan la semilla con app()->instance()
        $this->app->bind(Randomizer::class, fn () => new Randomizer(new Secure));

        // El profesional de encargo ve los documentos de sus encargos (encargo profesional, bloque D)
        $this->app->tag([AccesoDocumentosEncargo::class], DocumentoPolicy::ETIQUETA_ACCESOS);

        // Plazas y profesional de referencia (fase 3, bloque D): al liberar una plaza, la lista
        // de espera apunta al TSR vigente y se le avisa por AlertaService.
        $this->app->afterResolving(PrescripcionService::class, function ($prescripciones): void {
            $prescripciones->setTsrResolver(fn (int $ciudadanoId): ?int => app(AvisoPlazasService::class)->profesionalReferenciaDe($ciudadanoId));
            $prescripciones->setAvisoPlazaLibre(fn ($entrada) => app(AvisoPlazasService::class)->avisarPlazaLibre($entrada));
        });

        // Actividades (fase 6, F6-5): al cancelar una sesión se avisa a quien inscribió o prescribió
        $this->app->afterResolving(CicloVidaActividadService::class, function (CicloVidaActividadService $ciclo): void {
            $ciclo->setAvisoCancelacion(fn (int $usuarioId, $sesion, int $personas) => app(AvisoActividadesService::class)->avisarCancelacion($usuarioId, $sesion, $personas));
        });
    }

    /**
     * Carga la configuración, rutas, vistas y componentes del módulo.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));

        // El binding ignora el AmbitoUoScope para que la policy pueda emitir 403
        // en lugar de 404 cuando el usuario no tiene acceso al recurso.
        Route::bind('historia', fn ($value) => HistoriaSocial::withoutGlobalScopes()->findOrFail($value));
        // Igual para {plan}: PlanPage autoriza (y audita) en AccesoExpediente antes de leerlo.
        Route::bind('plan', fn ($value) => PlanDeIntervencion::withoutGlobalScopes()->findOrFail($value));

        $this->loadViewsFrom(module_path($this->moduleName, 'resources/views'), 'intervencion');

        $this->loadRoutesFrom(module_path($this->moduleName, 'routes/web.php'));

        Gate::policy(Apunte::class, ApuntePolicy::class);
        Gate::policy(Ficha::class, FichaPolicy::class);
        Gate::policy(PaseEscala::class, PaseEscalaPolicy::class);
        Gate::policy(PlanDeIntervencion::class, PlanDeIntervencionPolicy::class);

        Livewire::component('intervencion.agenda-page', AgendaPage::class);
        Livewire::component('intervencion.mis-casos-page', MisCasosPage::class);
        Livewire::component('intervencion.mis-encargos-page', MisEncargosPage::class);
        Livewire::component('intervencion.encargo-page', EncargoPage::class);
        Livewire::component('intervencion.buscar-ciudadano-page', BuscarCiudadanoPage::class);
        Livewire::component('intervencion.ciudadano-page', CiudadanoPage::class);
        Livewire::component('intervencion.derivaciones-plan', DerivacionesPlan::class);
        Livewire::component('intervencion.encargos-profesionales', EncargosProfesionales::class);
        Livewire::component('intervencion.cambio-referencia', CambioReferencia::class);
        Livewire::component('intervencion.registrar-valoracion-page', RegistrarValoracionPage::class);
        Livewire::component('intervencion.registrar-escala-page', RegistrarEscalaPage::class);
        Livewire::component('intervencion.recursos-page', RecursosPage::class);
        Livewire::component('intervencion.prescribir-recurso-modal', PrescribirRecursoModal::class);
        Livewire::component('intervencion.asignar-plaza-modal', AsignarPlazaModal::class);
    }
}
