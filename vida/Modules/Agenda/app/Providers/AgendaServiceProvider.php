<?php

namespace Modules\Agenda\Providers;

use App\Models\User;
use App\Models\UsuarioUo;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Agenda\Livewire\CuadranteMesComponent;
use Modules\Agenda\Livewire\ExcepcionesComponent;
use Modules\Agenda\Livewire\PerfilHorarioComponent;
use Modules\Agenda\Livewire\SemanaTypoComponent;
use Modules\Agenda\Livewire\Supervisor\AusenciasSupervisorPage;
use Modules\Agenda\Livewire\Supervisor\CuadranteSupervisorPage;
use Modules\Agenda\Livewire\Supervisor\EventosSupervisorPage;
use Modules\Agenda\Livewire\Supervisor\ExcepcionesSupervisorPage;
use Modules\Agenda\Livewire\Supervisor\Partials\ReasignacionPanel;
use Modules\Agenda\Livewire\Supervisor\Sidebar;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\ExcepcionProfesional;
use Modules\Agenda\Models\SolicitudCita;
use Modules\Agenda\Observers\CierreCitaObserver;
use Modules\Agenda\Observers\CitaObserver;
use Modules\Agenda\Observers\ExcepcionProfesionalObserver;
use Modules\Agenda\Observers\UsuarioProfesionalObserver;
use Modules\Agenda\Observers\UsuarioUoObserver;
use Modules\Agenda\Policies\CitaPolicy;
use Modules\Agenda\Policies\SolicitudCitaPolicy;
use Modules\Agenda\Services\Citas\AtencionCitaService;
use Modules\Agenda\Services\Citas\CitaPrevia\AdaptadorCitaPrevia;
use Modules\Agenda\Services\Citas\CitaPrevia\MockCitaPrevia;
use Modules\Atencion\Models\RegistroAtencion;
use Modules\Intervencion\Models\Apunte;

/**
 * Provider del módulo Agenda.
 *
 * Registra migraciones, vistas, rutas, observers y componentes Livewire
 * del módulo de citas, agendas y cuadrantes.
 */
class AgendaServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Agenda';

    /**
     * Registra los servicios del módulo en el contenedor.
     *
     * - Adaptador de Cita Previa: mock por defecto (principio 3.6), único por
     *   petición para que se puedan consultar sus notificaciones.
     * - AtencionCitaService es único por petición: guarda la corrección de
     *   supervisión en curso que consulta el observador de cierre.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(AdaptadorCitaPrevia::class, MockCitaPrevia::class);
        $this->app->singleton(AtencionCitaService::class);
    }

    /**
     * Arranca el módulo: migraciones, vistas, rutas, observers, políticas y Livewire.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));

        $this->loadViewsFrom(module_path($this->moduleName, 'resources/views'), 'agenda');

        $this->loadRoutesFrom(module_path($this->moduleName, 'routes/web.php'));

        Cita::observe(CitaObserver::class);
        ExcepcionProfesional::observe(ExcepcionProfesionalObserver::class);

        // Ningún profesional sin horario: al adscribirse recibe el del centro
        UsuarioUo::observe(UsuarioUoObserver::class);
        User::observe(UsuarioProfesionalObserver::class);

        // Cierre implícito: el apunte o el registro de atención completan la cita
        Apunte::observe(CierreCitaObserver::class);
        RegistroAtencion::observe(CierreCitaObserver::class);

        Gate::policy(Cita::class, CitaPolicy::class);
        Gate::policy(SolicitudCita::class, SolicitudCitaPolicy::class);

        Livewire::component('agenda.supervisor.sidebar', Sidebar::class);
        Livewire::component('agenda.supervisor.cuadrante-page', CuadranteSupervisorPage::class);
        Livewire::component('agenda.supervisor.ausencias-page', AusenciasSupervisorPage::class);
        Livewire::component('agenda.supervisor.excepciones-page', ExcepcionesSupervisorPage::class);
        Livewire::component('agenda.supervisor.eventos-page', EventosSupervisorPage::class);
        Livewire::component('agenda.supervisor.partials.reasignacion-panel', ReasignacionPanel::class);
        Livewire::component('agenda.semana-typo', SemanaTypoComponent::class);
        Livewire::component('agenda.perfil-horario', PerfilHorarioComponent::class);
        Livewire::component('agenda.excepciones', ExcepcionesComponent::class);
        Livewire::component('agenda.cuadrante-mes', CuadranteMesComponent::class);
    }
}
