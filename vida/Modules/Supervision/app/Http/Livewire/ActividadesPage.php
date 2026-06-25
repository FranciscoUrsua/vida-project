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
use Modules\Centro\Models\Sala;
use Modules\Centro\Models\SesionActividad;
use Modules\Centro\Models\TipoActividad;
use Modules\Usuarios\Models\Profesional;

/**
 * Pantalla de actividades grupales para el módulo de Supervisión.
 *
 * Gestiona el ciclo completo de actividades y sus sesiones:
 * - CRUD de actividades (modal alta/edición con picker de profesionales)
 * - CRUD de sesiones por actividad (modal de lista + formulario inline)
 *
 * El modal de sesiones alterna entre dos vistas:
 *   'lista'      → tabla de sesiones existentes + botón nueva sesión
 *   'formulario' → formulario de alta/edición con vuelta a la lista
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
 * @property bool $modalSesionesAbierto
 * @property int|null $actividadIdSesiones
 * @property string $sesionesModo
 * @property int|null $editandoSesionId
 * @property string $sesionFecha
 * @property string $sesionHoraInicio
 * @property string $sesionHoraFin
 * @property int|null $sesionSalaId
 * @property string $sesionEstado
 * @property int|null $sesionAforoTotal
 * @property int|null $sesionAforoPresc
 * @property string $sesionNotas
 * @property list<int> $sesionProfesionalesIds
 * @property string $sesionBusquedaProfesional
 * @property bool $sesionBuscarEnTodo
 */
#[Layout('layouts.supervision')]
class ActividadesPage extends Component
{
    // =========================================================================
    // Actividad — propiedades del formulario
    // =========================================================================

    /** @var bool Controla visibilidad del modal de actividad. */
    public bool $modalAbierto = false;

    /** @var int|null Id de la actividad en edición; null al crear. */
    public ?int $editandoId = null;

    public string $nombre = '';
    public ?int $tipoActividadId = null;
    public string $modoAcceso = 'libre';
    public ?int $aforoTotal = null;
    public ?int $aforoPresc = null;
    public string $fechaAlta = '';
    public bool $requiereInscripcion = false;

    /** @var list<int> IDs de profesionales responsables asignados a la actividad. */
    public array $profesionalesIds = [];

    /** @var string Búsqueda libre para el picker de profesionales de la actividad. */
    public string $busquedaProfesional = '';

    /** @var bool Si true, el picker de actividad busca en toda la organización. */
    public bool $buscarEnTodo = false;

    // =========================================================================
    // Sesiones — propiedades del modal
    // =========================================================================

    /** @var bool Controla visibilidad del modal de sesiones. */
    public bool $modalSesionesAbierto = false;

    /** @var int|null Actividad cuyas sesiones se están gestionando. */
    public ?int $actividadIdSesiones = null;

    /** @var string Vista activa dentro del modal: lista | formulario */
    public string $sesionesModo = 'lista';

    /** @var int|null Id de la sesión en edición; null al crear. */
    public ?int $editandoSesionId = null;

    public string $sesionFecha = '';
    public string $sesionHoraInicio = '';
    public string $sesionHoraFin = '';
    public ?int $sesionSalaId = null;
    public string $sesionEstado = 'programada';
    public ?int $sesionAforoTotal = null;
    public ?int $sesionAforoPresc = null;
    public string $sesionNotas = '';

    /** @var list<int> IDs de profesionales que dirigen esta sesión. */
    public array $sesionProfesionalesIds = [];

    /** @var string Búsqueda libre para el picker de profesionales de la sesión. */
    public string $sesionBusquedaProfesional = '';

    /** @var bool Si true, el picker de sesión busca en toda la organización. */
    public bool $sesionBuscarEnTodo = false;

    // =========================================================================
    // Inicialización
    // =========================================================================

    /**
     * Inicializa la fecha de alta de actividad con hoy.
     */
    public function mount(): void
    {
        $this->fechaAlta = Carbon::today()->toDateString();
    }

    // =========================================================================
    // Computed — actividades
    // =========================================================================

    /**
     * Actividades del centro del supervisor, ordenadas por fecha de alta descendente.
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
     * Catálogo de tipos de actividad activos.
     *
     * @return Collection<int, TipoActividad>
     */
    #[Computed]
    public function tiposActividad(): Collection
    {
        return TipoActividad::where('activo', true)->orderBy('nombre')->get();
    }

    /**
     * Profesionales disponibles para el picker de la actividad.
     * Modo centro: todos los de la UO del supervisor, excluidos los ya asignados.
     * Modo organización: búsqueda por nombre (mín. 2 chars, máx. 15 resultados).
     *
     * @return Collection<int, Profesional>
     */
    #[Computed]
    public function profesionalesParaSelector(): Collection
    {
        return $this->profesionalesDisponibles(
            $this->profesionalesIds,
            $this->buscarEnTodo,
            $this->busquedaProfesional
        );
    }

    /**
     * Profesionales actualmente asignados a la actividad en edición.
     *
     * @return Collection<int, Profesional>
     */
    #[Computed]
    public function profesionalesAsignados(): Collection
    {
        return $this->profesionalesPorIds($this->profesionalesIds);
    }

    // =========================================================================
    // Computed — sesiones
    // =========================================================================

    /**
     * Actividad cuyas sesiones se están gestionando en el modal.
     *
     * @return Actividad|null
     */
    #[Computed]
    public function actividadParaSesiones(): ?Actividad
    {
        if ($this->actividadIdSesiones === null) {
            return null;
        }

        return Actividad::find($this->actividadIdSesiones);
    }

    /**
     * Sesiones de la actividad seleccionada, ordenadas por fecha y hora.
     *
     * @return Collection<int, SesionActividad>
     */
    #[Computed]
    public function sesiones(): Collection
    {
        if ($this->actividadIdSesiones === null) {
            return new Collection();
        }

        return SesionActividad::where('actividad_id', $this->actividadIdSesiones)
            ->with('sala', 'profesionales.cargo')
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();
    }

    /**
     * Salas activas del centro del supervisor para el selector del formulario de sesión.
     *
     * @return Collection<int, Sala>
     */
    #[Computed]
    public function salasDelCentro(): Collection
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
     * Profesionales disponibles para el picker de la sesión.
     *
     * @return Collection<int, Profesional>
     */
    #[Computed]
    public function sesionProfesionalesParaSelector(): Collection
    {
        return $this->profesionalesDisponibles(
            $this->sesionProfesionalesIds,
            $this->sesionBuscarEnTodo,
            $this->sesionBusquedaProfesional
        );
    }

    /**
     * Profesionales actualmente asignados a la sesión en edición.
     *
     * @return Collection<int, Profesional>
     */
    #[Computed]
    public function sesionProfesionalesAsignados(): Collection
    {
        return $this->profesionalesPorIds($this->sesionProfesionalesIds);
    }

    // =========================================================================
    // Lifecycle hooks
    // =========================================================================

    /**
     * Limpia la búsqueda de profesionales de actividad al cambiar de modo.
     */
    public function updatedBuscarEnTodo(): void
    {
        $this->busquedaProfesional = '';
    }

    /**
     * Limpia la búsqueda de profesionales de sesión al cambiar de modo.
     */
    public function updatedSesionBuscarEnTodo(): void
    {
        $this->sesionBusquedaProfesional = '';
    }

    // =========================================================================
    // Acciones — modal actividad
    // =========================================================================

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
     * Abre el modal en modo edición cargando los datos de la actividad.
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
     * Añade el profesional indicado a la lista de la actividad.
     *
     * @param int $profesionalId
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
     * Quita el profesional indicado de la lista de la actividad.
     *
     * @param int $profesionalId
     * @return void
     */
    public function quitarProfesional(int $profesionalId): void
    {
        $this->profesionalesIds = array_values(
            array_filter($this->profesionalesIds, fn ($id) => $id !== $profesionalId)
        );
    }

    /**
     * Guarda el formulario de actividad (crea o actualiza) y sincroniza profesionales.
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

    // =========================================================================
    // Acciones — modal sesiones
    // =========================================================================

    /**
     * Abre el modal de sesiones para la actividad indicada en modo lista.
     *
     * @param int $actividadId
     * @return void
     */
    public function abrirSesiones(int $actividadId): void
    {
        $this->actividadIdSesiones = $actividadId;
        $this->sesionesModo        = 'lista';
        $this->modalSesionesAbierto = true;
        unset($this->sesiones);
        unset($this->actividadParaSesiones);
    }

    /**
     * Cambia el modal de sesiones al formulario en modo alta.
     *
     * @return void
     */
    public function nuevaSesion(): void
    {
        $this->reset([
            'editandoSesionId', 'sesionHoraFin', 'sesionSalaId',
            'sesionAforoTotal', 'sesionAforoPresc', 'sesionNotas',
            'sesionProfesionalesIds', 'sesionBusquedaProfesional', 'sesionBuscarEnTodo',
        ]);
        $this->sesionFecha      = Carbon::today()->toDateString();
        $this->sesionHoraInicio = '09:00';
        $this->sesionEstado     = 'programada';
        $this->sesionesModo     = 'formulario';
    }

    /**
     * Carga los datos de la sesión indicada y abre el formulario en modo edición.
     *
     * @param int $sesionId
     * @return void
     */
    public function editarSesion(int $sesionId): void
    {
        $sesion = SesionActividad::where('id', $sesionId)
            ->where('actividad_id', $this->actividadIdSesiones)
            ->firstOrFail();

        $this->editandoSesionId         = $sesion->id;
        $this->sesionFecha              = $sesion->fecha->toDateString();
        $this->sesionHoraInicio         = substr((string) $sesion->hora_inicio, 0, 5);
        $this->sesionHoraFin            = $sesion->hora_fin ? substr((string) $sesion->hora_fin, 0, 5) : '';
        $this->sesionSalaId             = $sesion->sala_id;
        $this->sesionEstado             = $sesion->estado;
        $this->sesionAforoTotal         = $sesion->aforo_total;
        $this->sesionAforoPresc         = $sesion->aforo_prescripcion;
        $this->sesionNotas              = $sesion->notas ?? '';
        $this->sesionProfesionalesIds   = $sesion->profesionales()->pluck('profesionales.id')->map(fn ($v) => (int) $v)->toArray();
        $this->sesionBusquedaProfesional = '';
        $this->sesionBuscarEnTodo       = false;
        $this->sesionesModo             = 'formulario';
    }

    /**
     * Guarda el formulario de sesión (crea o actualiza) y sincroniza profesionales.
     *
     * @return void
     */
    public function guardarSesion(): void
    {
        $this->validate([
            'sesionFecha'              => ['required', 'date'],
            'sesionHoraInicio'         => ['required', 'date_format:H:i'],
            'sesionHoraFin'            => ['nullable', 'date_format:H:i', 'after:sesionHoraInicio'],
            'sesionSalaId'             => ['nullable', 'integer', 'exists:salas,id'],
            'sesionEstado'             => ['required', 'in:programada,celebrada,cancelada'],
            'sesionAforoTotal'         => ['nullable', 'integer', 'min:1'],
            'sesionAforoPresc'         => ['nullable', 'integer', 'min:0'],
            'sesionNotas'              => ['nullable', 'string', 'max:1000'],
            'sesionProfesionalesIds.*' => ['integer', 'exists:profesionales,id'],
        ]);

        $datos = [
            'actividad_id'       => $this->actividadIdSesiones,
            'fecha'              => $this->sesionFecha,
            'hora_inicio'        => $this->sesionHoraInicio,
            'hora_fin'           => $this->sesionHoraFin ?: null,
            'sala_id'            => $this->sesionSalaId,
            'estado'             => $this->sesionEstado,
            'aforo_total'        => $this->sesionAforoTotal,
            'aforo_prescripcion' => $this->sesionAforoPresc,
            'notas'              => filled($this->sesionNotas) ? trim($this->sesionNotas) : null,
        ];

        if ($this->editandoSesionId !== null) {
            $sesion = SesionActividad::where('id', $this->editandoSesionId)
                ->where('actividad_id', $this->actividadIdSesiones)
                ->firstOrFail();
            $sesion->update($datos);
        } else {
            $sesion = SesionActividad::create($datos);
        }

        $sesion->profesionales()->sync($this->sesionProfesionalesIds);

        $this->sesionesModo = 'lista';
        unset($this->sesiones);
    }

    /**
     * Elimina (soft delete) la sesión indicada.
     *
     * @param int $sesionId
     * @return void
     */
    public function eliminarSesion(int $sesionId): void
    {
        SesionActividad::where('id', $sesionId)
            ->where('actividad_id', $this->actividadIdSesiones)
            ->firstOrFail()
            ->delete();

        unset($this->sesiones);
    }

    /**
     * Vuelve a la vista de lista dentro del modal de sesiones.
     *
     * @return void
     */
    public function volverALista(): void
    {
        $this->sesionesModo = 'lista';
    }

    /**
     * Añade el profesional indicado a la lista de la sesión.
     *
     * @param int $profesionalId
     * @return void
     */
    public function agregarSesionProfesional(int $profesionalId): void
    {
        if (! in_array($profesionalId, $this->sesionProfesionalesIds, true)) {
            $this->sesionProfesionalesIds[] = $profesionalId;
        }

        if ($this->sesionBuscarEnTodo) {
            $this->sesionBusquedaProfesional = '';
        }
    }

    /**
     * Quita el profesional indicado de la lista de la sesión.
     *
     * @param int $profesionalId
     * @return void
     */
    public function quitarSesionProfesional(int $profesionalId): void
    {
        $this->sesionProfesionalesIds = array_values(
            array_filter($this->sesionProfesionalesIds, fn ($id) => $id !== $profesionalId)
        );
    }

    // =========================================================================
    // Render
    // =========================================================================

    /**
     * Renderiza la pantalla de actividades grupales.
     *
     * @return View
     */
    public function render(): View
    {
        return view('supervision::livewire.actividades-page');
    }

    // =========================================================================
    // Helpers privados
    // =========================================================================

    /**
     * Profesionales disponibles para un picker, aplicando filtros de ámbito y búsqueda.
     *
     * @param list<int> $excluidos IDs ya asignados.
     * @param bool      $todoOrg   Si true, busca en toda la organización.
     * @param string    $busqueda  Texto libre (mín. 2 chars en modo org.).
     * @return Collection<int, Profesional>
     */
    private function profesionalesDisponibles(array $excluidos, bool $todoOrg, string $busqueda): Collection
    {
        $query = Profesional::where('activo', true)
            ->when(! empty($excluidos), fn ($q) => $q->whereNotIn('id', $excluidos))
            ->with('cargo')
            ->orderBy('apellido1')
            ->orderBy('nombre');

        if (! $todoOrg) {
            $uoIds = auth()->user()?->uoSubtreeIds() ?? [];
            $query->where(function ($q) use ($uoIds) {
                $q->whereIn('unidad_organizativa_id', $uoIds)
                    ->orWhereHas('usuario', fn ($q2) => $q2->whereHas('adscripcionesVigentes', fn ($q3) => $q3->whereIn('unidad_organizativa_id', $uoIds)));
            });

            return $query->get();
        }

        if (mb_strlen(trim($busqueda)) < 2) {
            return new Collection();
        }

        $termino = '%' . trim($busqueda) . '%';

        return $query->where(function ($q) use ($termino) {
            $q->where('nombre', 'ilike', $termino)
                ->orWhere('apellido1', 'ilike', $termino)
                ->orWhere('apellido2', 'ilike', $termino);
        })->limit(15)->get();
    }

    /**
     * Devuelve una colección de profesionales a partir de una lista de IDs.
     *
     * @param list<int> $ids
     * @return Collection<int, Profesional>
     */
    private function profesionalesPorIds(array $ids): Collection
    {
        if (empty($ids)) {
            return new Collection();
        }

        return Profesional::whereIn('id', $ids)
            ->with('cargo')
            ->orderBy('apellido1')
            ->orderBy('nombre')
            ->get();
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
