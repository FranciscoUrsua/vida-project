<?php

namespace Modules\Agenda\Livewire\Supervisor;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Agenda\Models\EventoAgenda;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\Sala;
use Modules\Usuarios\Models\Profesional;

/**
 * Página de eventos internos del centro para el supervisor.
 *
 * Gestiona reuniones, formaciones y sesiones de equipo que bloquean slots
 * de los profesionales convocados. Al crear un evento, el modelo EventoAgenda
 * bloquea los slots disponibles de los convocados y detecta conflictos de espacio.
 * Solo se pueden editar eventos con fecha >= hoy.
 *
 * @property bool $mostrarFormulario
 * @property bool $mostrarModalEdicion
 * @property int|null $editandoId
 * @property array{nombre: string, fecha: string, hora_inicio: string, duracion_minutos: string, tipo_evento: string, espacio_id: string, profesionales_ids: array<int, int>} $form
 * @property array{nombre: string, fecha: string, hora_inicio: string, duracion_minutos: string, tipo_evento: string, espacio_id: string, profesionales_ids: array<int, int>} $formEdicion
 * @property-read Collection<int, EventoAgenda> $eventosProximos
 * @property-read Collection<int, Sala> $espaciosDelCentro
 * @property-read Collection<int, Profesional> $profesionalesDelCentro
 * @property bool $hayConflictoEspacio
 */
#[Layout('agenda::layouts.agenda-supervisor')]
class EventosSupervisorPage extends Component
{
    /** @var bool Controla la visibilidad del formulario de nuevo evento */
    public bool $mostrarFormulario = false;

    /** @var bool Controla la visibilidad del modal de edición */
    public bool $mostrarModalEdicion = false;

    /** @var int|null ID del evento que se está editando */
    public ?int $editandoId = null;

    /** @var bool Aviso de conflicto de espacio detectado al crear */
    public bool $hayConflictoEspacio = false;

    /**
     * Campos del formulario de nuevo evento.
     *
     * @var array{nombre: string, fecha: string, hora_inicio: string, duracion_minutos: string, tipo_evento: string, espacio_id: string, profesionales_ids: array<int, int>}
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

    /**
     * Campos del formulario de edición de evento.
     *
     * @var array{nombre: string, fecha: string, hora_inicio: string, duracion_minutos: string, tipo_evento: string, espacio_id: string, profesionales_ids: array<int, int>}
     */
    public array $formEdicion = [
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
     * Salas activas del centro para el selector del formulario.
     *
     * @return Collection<int, Sala>
     */
    #[Computed]
    public function espaciosDelCentro(): Collection
    {
        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return new Collection();
        }

        return Sala::where('centro_id', $centro->id)
            ->where('activa', true)
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Profesionales de la UO del supervisor para el selector de convocados.
     *
     * Usa la jerarquía completa de UOs (subtree) igual que EquipoPage,
     * de modo que aparecen todos los profesionales del centro aunque no
     * tengan aún un PerfilHorarioProfesional asignado.
     *
     * @return Collection<int, Profesional>
     */
    #[Computed]
    public function profesionalesDelCentro(): Collection
    {
        $uoIds = auth()->user()?->uoSubtreeIds() ?? [];

        if (empty($uoIds)) {
            return new Collection();
        }

        return Profesional::where(function ($q) use ($uoIds) {
            $q->whereIn('unidad_organizativa_id', $uoIds)
                ->orWhereHas('usuario', fn ($q2) => $q2->whereHas(
                    'adscripcionesVigentes',
                    fn ($q3) => $q3->whereIn('unidad_organizativa_id', $uoIds)
                ));
        })
            ->with('usuario')
            ->orderBy('apellido1')
            ->orderBy('nombre')
            ->get();
    }

    // -------------------------------------------------------------------------
    // Acciones — crear
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
            'form.nombre'              => ['required', 'string', 'max:200'],
            'form.fecha'               => ['required', 'date'],
            'form.hora_inicio'         => ['required', 'date_format:H:i'],
            'form.duracion_minutos'    => ['required', 'integer', 'min:5', 'max:480'],
            'form.tipo_evento'         => ['required', 'string', 'max:100'],
            'form.espacio_id'          => ['nullable', 'integer', 'exists:salas,id'],
            'form.profesionales_ids'   => ['array'],
            'form.profesionales_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return;
        }

        $horaFin   = $this->calcularHoraFin($this->form['hora_inicio'], (int) $this->form['duracion_minutos']);
        $espacioId = filled($this->form['espacio_id']) ? (int) $this->form['espacio_id'] : null;

        $evento = EventoAgenda::create([
            'centro_id'     => $centro->id,
            'titulo'        => trim($this->form['nombre']),
            'fecha'         => $this->form['fecha'],
            'hora_inicio'   => $this->form['hora_inicio'],
            'hora_fin'      => $horaFin,
            'tipo_evento'   => $this->form['tipo_evento'],
            'espacio_id'    => $espacioId,
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

    // -------------------------------------------------------------------------
    // Acciones — editar
    // -------------------------------------------------------------------------

    /**
     * Carga el evento en el formulario de edición y abre el modal.
     *
     * Solo actúa si el evento existe en el centro del supervisor y su fecha
     * es igual o posterior a hoy (no se editan eventos ya ocurridos).
     *
     * @param int $eventoId
     * @return void
     */
    public function abrirEdicion(int $eventoId): void
    {
        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return;
        }

        $evento = EventoAgenda::where('id', $eventoId)
            ->where('centro_id', $centro->id)
            ->where('fecha', '>=', now()->toDateString())
            ->with('profesionales')
            ->first();

        if ($evento === null) {
            return;
        }

        $this->editandoId   = $eventoId;
        $this->formEdicion  = [
            'nombre'            => $evento->titulo,
            'fecha'             => $evento->fecha->toDateString(),
            'hora_inicio'       => substr($evento->hora_inicio, 0, 5),
            'duracion_minutos'  => (string) $this->calcularDuracion($evento->hora_inicio, $evento->hora_fin),
            'tipo_evento'       => $evento->tipo_evento,
            'espacio_id'        => (string) ($evento->espacio_id ?? ''),
            'profesionales_ids' => $evento->profesionales->pluck('id')->toArray(),
        ];
        $this->mostrarModalEdicion = true;
    }

    /**
     * Persiste los cambios del evento en edición.
     *
     * Los convocados se sincronizan (sync) sin tocar el estado de los slots,
     * que ya fueron gestionados al crear el evento o al eliminar convocados
     * previos. La hora de fin se recalcula a partir de la nueva duración.
     *
     * @return void
     */
    public function actualizar(): void
    {
        $this->validate([
            'formEdicion.nombre'              => ['required', 'string', 'max:200'],
            'formEdicion.fecha'               => ['required', 'date'],
            'formEdicion.hora_inicio'         => ['required', 'date_format:H:i'],
            'formEdicion.duracion_minutos'    => ['required', 'integer', 'min:5', 'max:480'],
            'formEdicion.tipo_evento'         => ['required', 'string', 'max:100'],
            'formEdicion.espacio_id'          => ['nullable', 'integer', 'exists:salas,id'],
            'formEdicion.profesionales_ids'   => ['array'],
            'formEdicion.profesionales_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return;
        }

        $evento = EventoAgenda::where('id', $this->editandoId)
            ->where('centro_id', $centro->id)
            ->where('fecha', '>=', now()->toDateString())
            ->first();

        if ($evento === null) {
            return;
        }

        $horaFin   = $this->calcularHoraFin($this->formEdicion['hora_inicio'], (int) $this->formEdicion['duracion_minutos']);
        $espacioId = filled($this->formEdicion['espacio_id']) ? (int) $this->formEdicion['espacio_id'] : null;

        $evento->update([
            'titulo'      => trim($this->formEdicion['nombre']),
            'fecha'       => $this->formEdicion['fecha'],
            'hora_inicio' => $this->formEdicion['hora_inicio'],
            'hora_fin'    => $horaFin,
            'tipo_evento' => $this->formEdicion['tipo_evento'],
            'espacio_id'  => $espacioId,
        ]);

        // Sincroniza convocados sin volver a bloquear slots (mantiene el estado actual de los slots)
        $evento->profesionales()->sync($this->formEdicion['profesionales_ids']);

        $this->mostrarModalEdicion = false;
        $this->editandoId          = null;
        unset($this->eventosProximos);
    }

    /**
     * Cierra el modal de edición sin guardar cambios.
     *
     * @return void
     */
    public function cerrarEdicion(): void
    {
        $this->mostrarModalEdicion = false;
        $this->editandoId          = null;
        $this->resetValidation();
    }

    // -------------------------------------------------------------------------
    // Acciones — eliminar
    // -------------------------------------------------------------------------

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
     * @param string $horaInicio Formato HH:MM o HH:MM:SS
     * @param int $duracionMinutos
     * @return string Formato HH:MM
     */
    private function calcularHoraFin(string $horaInicio, int $duracionMinutos): string
    {
        [$h, $m] = explode(':', $horaInicio);
        $totalMin = (int) $h * 60 + (int) $m + $duracionMinutos;

        return sprintf('%02d:%02d', intdiv($totalMin, 60) % 24, $totalMin % 60);
    }

    /**
     * Calcula los minutos de duración entre dos horas en formato HH:MM o HH:MM:SS.
     *
     * @param string $horaInicio
     * @param string $horaFin
     * @return int
     */
    private function calcularDuracion(string $horaInicio, string $horaFin): int
    {
        [$hi, $mi] = explode(':', $horaInicio);
        [$hf, $mf] = explode(':', $horaFin);

        return ((int) $hf * 60 + (int) $mf) - ((int) $hi * 60 + (int) $mi);
    }
}
