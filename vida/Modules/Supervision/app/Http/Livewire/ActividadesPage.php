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
use Modules\Usuarios\Models\Profesional;

/**
 * Pantalla de actividades grupales para el módulo de Supervisión.
 *
 * Permite al supervisor crear y editar las actividades programadas
 * en su centro: cursos, talleres, grupos de apoyo.
 * El modal actúa en modo alta cuando editandoId es null, y en modo
 * edición cuando editandoId contiene el id de la actividad a modificar.
 *
 * Cada actividad debe tener al menos un profesional responsable asignado.
 * El picker de profesionales muestra el equipo del centro por defecto.
 * Con el checkbox «toda la organización» se activa una búsqueda por nombre
 * que devuelve hasta 15 resultados para evitar cargar miles de registros.
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
 * @property list<int> $profesionalesIds
 * @property string $busquedaProfesional
 * @property bool $buscarEnTodo
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

    /** @var list<int> IDs de profesionales responsables asignados a la actividad en curso. */
    public array $profesionalesIds = [];

    /** @var string Texto de búsqueda libre para filtrar profesionales de toda la organización. */
    public string $busquedaProfesional = '';

    /** @var bool Si true, el picker busca en toda la organización en lugar del equipo del centro. */
    public bool $buscarEnTodo = false;

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
     * Profesionales disponibles para añadir al picker.
     *
     * Modo centro (buscarEnTodo=false): devuelve todos los profesionales activos
     * de la UO del supervisor, excluidos los ya asignados.
     *
     * Modo organización (buscarEnTodo=true): requiere al menos 2 caracteres en
     * busquedaProfesional; devuelve hasta 15 coincidencias por nombre o apellido.
     *
     * @return Collection<int, Profesional>
     */
    #[Computed]
    public function profesionalesParaSelector(): Collection
    {
        $base = Profesional::where('activo', true)
            ->when(! empty($this->profesionalesIds), fn ($q) => $q->whereNotIn('id', $this->profesionalesIds))
            ->with('cargo')
            ->orderBy('apellido1')
            ->orderBy('nombre');

        if (! $this->buscarEnTodo) {
            $uoIds = auth()->user()?->uoSubtreeIds() ?? [];
            $base->where(function ($q) use ($uoIds) {
                $q->whereIn('unidad_organizativa_id', $uoIds)
                    ->orWhereHas('usuario', fn ($q2) => $q2->whereHas('adscripcionesVigentes', fn ($q3) => $q3->whereIn('unidad_organizativa_id', $uoIds)));
            });

            return $base->get();
        }

        // Modo toda la organización: exige mínimo 2 caracteres
        if (mb_strlen(trim($this->busquedaProfesional)) < 2) {
            return new Collection();
        }

        $termino = '%' . trim($this->busquedaProfesional) . '%';

        return $base->where(function ($q) use ($termino) {
            $q->where('nombre', 'ilike', $termino)
                ->orWhere('apellido1', 'ilike', $termino)
                ->orWhere('apellido2', 'ilike', $termino);
        })->limit(15)->get();
    }

    /**
     * Colección de profesionales actualmente asignados, para mostrar en la lista del modal.
     *
     * @return Collection<int, Profesional>
     */
    #[Computed]
    public function profesionalesAsignados(): Collection
    {
        if (empty($this->profesionalesIds)) {
            return new Collection();
        }

        return Profesional::whereIn('id', $this->profesionalesIds)
            ->with('cargo')
            ->orderBy('apellido1')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Resetea la búsqueda al cambiar entre modo centro y modo organización.
     *
     * @return void
     */
    public function updatedBuscarEnTodo(): void
    {
        $this->busquedaProfesional = '';
    }

    /**
     * Abre el modal en modo alta con el formulario limpio.
     *
     * @return void
     */
    public function abrirModal(): void
    {
        $this->reset([
            'editandoId', 'nombre', 'tipoActividadId', 'modoAcceso',
            'aforoTotal', 'aforoPresc', 'requiereInscripcion',
            'profesionalesIds', 'busquedaProfesional', 'buscarEnTodo',
        ]);
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

        $this->editandoId           = $actividad->id;
        $this->nombre               = $actividad->nombre;
        $this->tipoActividadId      = $actividad->tipo_actividad_id;
        $this->modoAcceso           = $actividad->modo_acceso;
        $this->aforoTotal           = $actividad->aforo_total;
        $this->aforoPresc           = $actividad->aforo_prescripcion;
        $this->fechaAlta            = $actividad->fecha_alta->toDateString();
        $this->requiereInscripcion  = (bool) $actividad->requiere_inscripcion_centro;
        $this->profesionalesIds     = $actividad->profesionales()->pluck('profesionales.id')->map(fn ($v) => (int) $v)->toArray();
        $this->busquedaProfesional  = '';
        $this->buscarEnTodo         = false;
        $this->modalAbierto         = true;
    }

    /**
     * Añade el profesional indicado a la lista de asignados.
     * En modo organización limpia la búsqueda tras añadir para facilitar
     * buscar otro profesional sin borrar manualmente.
     *
     * @param int $profesionalId ID del profesional a añadir.
     * @return void
     */
    public function agregarProfesional(int $profesionalId): void
    {
        if (! in_array($profesionalId, $this->profesionalesIds, true)) {
            $this->profesionalesIds[] = $profesionalId;
        }

        if ($this->buscarEnTodo) {
            $this->busquedaProfesional = '';
        }
    }

    /**
     * Elimina un profesional de la lista de asignados del modal.
     *
     * @param int $profesionalId ID del profesional a quitar.
     * @return void
     */
    public function quitarProfesional(int $profesionalId): void
    {
        $this->profesionalesIds = array_values(
            array_filter($this->profesionalesIds, fn ($id) => $id !== $profesionalId)
        );
    }

    /**
     * Guarda los datos del formulario: crea una nueva actividad o actualiza la existente.
     * Sincroniza los profesionales responsables en la tabla pivot.
     *
     * @return void
     */
    public function guardar(): void
    {
        $this->validate(
            [
                'nombre'             => ['required', 'string', 'max:200'],
                'tipoActividadId'    => ['required', 'integer', 'exists:tipos_actividad,id'],
                'modoAcceso'         => ['required', 'in:libre,prescripcion,mixta'],
                'aforoTotal'         => ['nullable', 'integer', 'min:1'],
                'aforoPresc'         => ['nullable', 'integer', 'min:0'],
                'fechaAlta'          => ['required', 'date'],
                'profesionalesIds'   => ['required', 'array', 'min:1'],
                'profesionalesIds.*' => ['integer', 'exists:profesionales,id'],
            ],
            [
                'profesionalesIds.required' => 'Debes asignar al menos un profesional responsable.',
                'profesionalesIds.min'      => 'Debes asignar al menos un profesional responsable.',
            ]
        );

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
            $actividad = Actividad::where('id', $this->editandoId)
                ->when($centro !== null, fn ($q) => $q->where('centro_id', $centro->id))
                ->firstOrFail();
            $actividad->update($datos);
        } else {
            $centro = $this->centroDelSupervisor();

            if ($centro === null) {
                $this->addError('nombre', 'No se ha encontrado un centro asociado a tu unidad organizativa.');
                return;
            }

            $actividad = Actividad::create(array_merge($datos, [
                'centro_id' => $centro->id,
                'activa'    => true,
            ]));
        }

        $actividad->profesionales()->sync($this->profesionalesIds);

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
