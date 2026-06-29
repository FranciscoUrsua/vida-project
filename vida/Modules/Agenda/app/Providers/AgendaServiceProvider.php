<?php

namespace Modules\Agenda\Providers;

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
use Modules\Agenda\Observers\CitaObserver;
use Modules\Agenda\Observers\ExcepcionProfesionalObserver;

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
     */
    public function register(): void
    {
        //
    }

    /**
     * Arranca el módulo: migraciones, vistas, rutas, observers y Livewire.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));

        $this->loadViewsFrom(module_path($this->moduleName, 'resources/views'), 'agenda');

        $this->loadRoutesFrom(module_path($this->moduleName, 'routes/web.php'));

        Cita::observe(CitaObserver::class);
        ExcepcionProfesional::observe(ExcepcionProfesionalObserver::class);

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
