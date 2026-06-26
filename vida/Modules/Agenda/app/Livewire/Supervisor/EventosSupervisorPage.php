<?php

namespace Modules\Agenda\Livewire\Supervisor;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Agenda\Models\EventoAgenda;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\Espacio;

/**
 * Página de eventos internos del centro para el supervisor.
 *
 * Gestiona reuniones, formaciones y sesiones de equipo que bloquean slots
 * de los profesionales convocados. Al crear un evento, el modelo EventoAgenda
 * bloquea los slots disponibles de los convocados y detecta conflictos de espacio.
 *
 * @property bool $mostrarFormulario
 * @property array $form
 * @property bool $hayConflictoEspacio
 */
#[Layout('agenda::layouts.agenda-supervisor')]
class EventosSupervisorPage extends Component
{
    /** @var bool Controla la visibilidad del formulario de nuevo evento */
    public bool $mostrarFormulario = false;

    /** @var bool Aviso de conflicto de espacio detectado al crear */
    public bool $hayConflictoEspacio = false;

    /**
     * Campos del formulario de nuevo evento.
     *
     * @var array{nombre: string, fecha: string, hora_inicio: string, duracion_minutos: string, tipo_evento: string, espacio_id: string, profesionales_ids: array<int>}
     */
    public array $form = [
        'nombre'            => '',
        'fecha'             => '',
        'hora_inicio'       => '',
        'duracion_minutos'  => '',
        'tipo_evento'       => '',
        'espacio_id'        => '',
        'profesionales_ids' => [],
    ];

    // -------------------------------------------------------------------------
    // Computed
    // -------------------------------------------------------------------------

    /**
     * Eventos próximos del centro con fecha >= hoy, ordenados por inicio.
     *
     * @return Collection<int, EventoAgenda>
     */
    #[Computed]
    public function eventosProximos(): Collection
    {
        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return new Collection();
        }

        return EventoAgenda::where('centro_id', $centro->id)
            ->where('fecha', '>=', now()->toDateString())
            ->with(['espacio', 'profesionales', 'profesionales.profesional'])
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();
    }

    /**
     * Espacios activos del centro para el selector del formulario.
     *
     * @return Collection<int, Espacio>
     */
    #[Computed]
    public function espaciosDelCentro(): Collection
    {
        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return new Collection();
        }

        return Espacio::where('centro_id', $centro->id)
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Usuarios con perfil horario activo en el centro del supervisor.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function profesionalesDelCentro(): Collection
    {
        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return new Collection();
        }

        $userIds = PerfilHorarioProfesional::where('centro_id', $centro->id)
            ->where('activo', true)
            ->pluck('usuario_id');

        return User::whereIn('id', $userIds)
            ->with('profesional')
            ->orderBy('name')
            ->get();
    }

    // -------------------------------------------------------------------------
    // Acciones
    // -------------------------------------------------------------------------

    /**
     * Crea el evento y bloquea los slots de los convocados.
     *
     * Muestra aviso si hay conflicto de espacio pero no bloquea la creación.
     *
     * @return void
     */
    public function crear(): void
    {
        $this->validate([
            'form.nombre'           => ['required', 'string', 'max:200'],
            'form.fecha'            => ['required', 'date'],
            'form.hora_inicio'      => ['required', 'date_format:H:i'],
            'form.duracion_minutos' => ['required', 'integer', 'min:5', 'max:480'],
            'form.tipo_evento'      => ['required', 'string', 'max:100'],
            'form.espacio_id'       => ['nullable', 'integer', 'exists:espacios,id'],
            'form.profesionales_ids' => ['array'],
            'form.profesionales_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return;
        }

        $duracion   = (int) $this->form['duracion_minutos'];
        $horaFin    = $this->calcularHoraFin($this->form['hora_inicio'], $duracion);
        $espacioId  = filled($this->form['espacio_id']) ? (int) $this->form['espacio_id'] : null;

        $evento = EventoAgenda::create([
            'centro_id'    => $centro->id,
            'titulo'       => trim($this->form['nombre']),
            'fecha'        => $this->form['fecha'],
            'hora_inicio'  => $this->form['hora_inicio'],
            'hora_fin'     => $horaFin,
            'tipo_evento'  => $this->form['tipo_evento'],
            'espacio_id'   => $espacioId,
            'creado_por_id' => auth()->id(),
        ]);

        if (! empty($this->form['profesionales_ids'])) {
            $evento->agregarProfesionales($this->form['profesionales_ids']);
        }

        $this->hayConflictoEspacio = $evento->detectarConflictoEspacio();

        $this->form = [
            'nombre' => '', 'fecha' => '', 'hora_inicio' => '', 'duracion_minutos' => '',
            'tipo_evento' => '', 'espacio_id' => '', 'profesionales_ids' => [],
        ];
        $this->mostrarFormulario = false;
        unset($this->eventosProximos);
    }

    /**
     * Elimina el evento y libera los slots bloqueados.
     *
     * @param int $eventoId
     * @return void
     */
    public function eliminar(int $eventoId): void
    {
        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return;
        }

        $evento = EventoAgenda::where('id', $eventoId)
            ->where('centro_id', $centro->id)
            ->first();

        if ($evento === null) {
            return;
        }

        // El Observer de EventoAgenda libera los slots al eliminar
        $evento->delete();
        unset($this->eventosProximos);
    }

    /**
     * Renderiza la pantalla de eventos internos.
     *
     * @return View
     */
    public function render(): View
    {
        return view('agenda::livewire.supervisor.eventos-supervisor-page');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resuelve el Centro del supervisor autenticado.
     *
     * @return Centro|null
     */
    private function centroDelSupervisor(): ?Centro
    {
        $uoId = auth()->user()?->uosActivas()->first()?->id;

        return $uoId ? Centro::where('unidad_organizativa_id', $uoId)->first() : null;
    }

    /**
     * Calcula la hora de fin sumando los minutos de duración a la hora de inicio.
     *
     * @param string $horaInicio Formato HH:MM
     * @param int $duracionMinutos
     * @return string Formato HH:MM
     */
    private function calcularHoraFin(string $horaInicio, int $duracionMinutos): string
    {
        [$h, $m] = explode(':', $horaInicio);
        $totalMin = (int) $h * 60 + (int) $m + $duracionMinutos;

        return sprintf('%02d:%02d', intdiv($totalMin, 60) % 24, $totalMin % 60);
    }
}
