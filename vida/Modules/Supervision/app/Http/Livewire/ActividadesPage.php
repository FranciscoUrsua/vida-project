<?php

namespace Modules\Supervision\Http\Livewire;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Centro\Models\Actividad;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\TipoActividad;

/**
 * Pantalla de actividades grupales para el módulo de Supervisión.
 *
 * Permite al supervisor crear y editar las actividades programadas
 * en su centro: cursos, talleres, grupos de apoyo.
 * El modal actúa en modo alta cuando editandoId es null, y en modo
 * edición cuando editandoId contiene el id de la actividad a modificar.
 *
 * @property bool $modalAbierto
 * @property int|null $editandoId
 * @property string $nombre
 * @property int|null $tipoActividadId
 * @property string $modoAcceso
 * @property int|null $aforoTotal
 * @property int|null $aforoPresc
 * @property string $fechaAlta
 * @property bool $requiereInscripcion
 */
#[Layout('layouts.supervision')]
class ActividadesPage extends Component
{
    /** @var bool Controla visibilidad del modal. */
    public bool $modalAbierto = false;

    /** @var int|null Id de la actividad en edición; null cuando se está creando una nueva. */
    public ?int $editandoId = null;

    public string $nombre = '';
    public ?int $tipoActividadId = null;
    public string $modoAcceso = 'libre';
    public ?int $aforoTotal = null;
    public ?int $aforoPresc = null;
    public string $fechaAlta = '';
    public bool $requiereInscripcion = false;

    /**
     * Inicializa la fecha de alta con la fecha actual.
     */
    public function mount(): void
    {
        $this->fechaAlta = Carbon::today()->toDateString();
    }

    /**
     * Lista de actividades del centro del supervisor, ordenadas por fecha de alta descendente.
     *
     * @return Collection<int, Actividad>
     */
    #[Computed]
    public function actividades(): Collection
    {
        $centro = $this->centroDelSupervisor();

        if ($centro === null) {
            return new Collection();
        }

        return Actividad::where('centro_id', $centro->id)
            ->with('tipoActividad')
            ->orderByDesc('fecha_alta')
            ->get();
    }

    /**
     * Catálogo de tipos de actividad activos para el selector del modal.
     *
     * @return Collection<int, TipoActividad>
     */
    #[Computed]
    public function tiposActividad(): Collection
    {
        return TipoActividad::where('activo', true)->orderBy('nombre')->get();
    }

    /**
     * Abre el modal en modo alta con el formulario limpio.
     *
     * @return void
     */
    public function abrirModal(): void
    {
        $this->reset(['editandoId', 'nombre', 'tipoActividadId', 'modoAcceso', 'aforoTotal', 'aforoPresc', 'requiereInscripcion']);
        $this->fechaAlta = Carbon::today()->toDateString();
        $this->modalAbierto = true;
    }

    /**
     * Abre el modal en modo edición cargando los datos de la actividad indicada.
     *
     * Solo permite editar actividades del propio centro del supervisor.
     *
     * @param int $id Id de la actividad a editar.
     * @return void
     */
    public function abrirEdicion(int $id): void
    {
        $centro = $this->centroDelSupervisor();

        $actividad = Actividad::where('id', $id)
            ->when($centro !== null, fn ($q) => $q->where('centro_id', $centro->id))
            ->firstOrFail();

        $this->editandoId          = $actividad->id;
        $this->nombre              = $actividad->nombre;
        $this->tipoActividadId     = $actividad->tipo_actividad_id;
        $this->modoAcceso          = $actividad->modo_acceso;
        $this->aforoTotal          = $actividad->aforo_total;
        $this->aforoPresc          = $actividad->aforo_prescripcion;
        $this->fechaAlta           = $actividad->fecha_alta->toDateString();
        $this->requiereInscripcion = (bool) $actividad->requiere_inscripcion_centro;
        $this->modalAbierto        = true;
    }

    /**
     * Guarda los datos del formulario: crea una nueva actividad o actualiza la existente.
     *
     * @return void
     */
    public function guardar(): void
    {
        $this->validate([
            'nombre'          => ['required', 'string', 'max:200'],
            'tipoActividadId' => ['required', 'integer', 'exists:tipos_actividad,id'],
            'modoAcceso'      => ['required', 'in:libre,prescripcion,mixta'],
            'aforoTotal'      => ['nullable', 'integer', 'min:1'],
            'aforoPresc'      => ['nullable', 'integer', 'min:0'],
            'fechaAlta'       => ['required', 'date'],
        ]);

        $datos = [
            'tipo_actividad_id'           => $this->tipoActividadId,
            'nombre'                      => trim($this->nombre),
            'modo_acceso'                 => $this->modoAcceso,
            'aforo_total'                 => $this->aforoTotal,
            'aforo_prescripcion'          => $this->modoAcceso !== 'libre' ? $this->aforoPresc : null,
            'requiere_inscripcion_centro' => $this->requiereInscripcion,
            'fecha_alta'                  => $this->fechaAlta,
        ];

        if ($this->editandoId !== null) {
            $centro = $this->centroDelSupervisor();
            Actividad::where('id', $this->editandoId)
                ->when($centro !== null, fn ($q) => $q->where('centro_id', $centro->id))
                ->firstOrFail()
                ->update($datos);
        } else {
            $centro = $this->centroDelSupervisor();

            if ($centro === null) {
                $this->addError('nombre', 'No se ha encontrado un centro asociado a tu unidad organizativa.');
                return;
            }

            Actividad::create(array_merge($datos, [
                'centro_id' => $centro->id,
                'activa'    => true,
            ]));
        }

        $this->modalAbierto = false;
        unset($this->actividades);
    }

    /**
     * Renderiza la pantalla de actividades grupales.
     *
     * @return View
     */
    public function render(): View
    {
        return view('supervision::livewire.actividades-page');
    }

    /**
     * Resuelve el Centro asociado a la primera UO activa del supervisor autenticado.
     *
     * @return Centro|null
     */
    private function centroDelSupervisor(): ?Centro
    {
        $uoId = auth()->user()?->uosActivas()->first()?->id;

        if ($uoId === null) {
            return null;
        }

        return Centro::where('unidad_organizativa_id', $uoId)->first();
    }
}
