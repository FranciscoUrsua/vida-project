<?php

namespace Modules\Supervision\Http\Livewire;

use App\Services\PresentacionPersona;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Centro\Enums\ViaInscripcion;
use Modules\Centro\Models\Actividad;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\InscripcionSesion;
use Modules\Centro\Models\Sala;
use Modules\Centro\Models\SesionActividad;
use Modules\Centro\Models\TipoActividad;
use Modules\Centro\Services\Actividades\CicloVidaActividadService;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Usuarios\Models\Profesional;

/**
 * Pantalla de actividades grupales para el módulo de Supervisión.
 *
 * Gestiona el ciclo completo de actividades y sus sesiones:
 * - Alta y edición de actividades (modal con picker de profesionales y prestación vinculada)
 * - Baja de actividades con motivo; las de baja salen del listado salvo con el filtro (F6-5)
 * - Sesiones por actividad: alta, edición, cancelación con motivo, «Marcar como celebrada»
 *   y borrado solo de las que no tienen inscripciones ni prescripciones (F6-5)
 * - Inscritos de cada sesión, con la identidad de las personas protegidas solo para quien
 *   las puede ver (RN-17)
 *
 * El estado de una sesión no se edita en el formulario: cambia solo por las acciones, que
 * pasan por CicloVidaActividadService (cancelar da de baja las inscripciones y avisa).
 *
 * El modal de sesiones alterna entre tres vistas:
 *   'lista'      → tabla de sesiones existentes + botón nueva sesión
 *   'formulario' → formulario de alta/edición con vuelta a la lista
 *   'inscritos'  → inscritos de una sesión y aforo ocupado por vía
 *
 * @property bool $modalAbierto
 * @property int|null $editandoId
 * @property string $nombre
 * @property int|null $tipoActividadId
 * @property string $modoAcceso
 * @property int|null $aforoTotal
 * @property int|null $aforoPresc
 * @property int|null $prestacionId
 * @property bool $verBajas
 * @property int|null $bajaActividadId
 * @property string $motivoBaja
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
 * @property int|null $cancelandoSesionId
 * @property string $motivoCancelacion
 * @property int|null $inscritosSesionId
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

    /** @var int|null Prestación del catálogo vinculada: al inscribir se crea la de la persona. */
    public ?int $prestacionId = null;

    /** @var bool Si el listado muestra también las actividades dadas de baja. */
    public bool $verBajas = false;

    /** @var int|null Actividad que se va a dar de baja (modal de confirmación). */
    public ?int $bajaActividadId = null;

    public string $motivoBaja = '';

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

    /** @var int|null Sesión que se va a cancelar (formulario de motivo en la lista). */
    public ?int $cancelandoSesionId = null;

    public string $motivoCancelacion = '';

    /** @var int|null Sesión cuyos inscritos se muestran. */
    public ?int $inscritosSesionId = null;

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
            return new Collection;
        }

        return Actividad::where('centro_id', $centro->id)
            ->when(! $this->verBajas, fn ($q) => $q->where('activa', true)->whereNull('fecha_baja'))
            ->with('tipoActividad')
            ->orderByDesc('fecha_alta')
            ->get();
    }

    /**
     * Prestaciones que ofrece el centro (catálogo efectivo), para vincular a la actividad.
     *
     * @return \Illuminate\Support\Collection<int, Prestacion>
     */
    #[Computed]
    public function prestacionesDelCentro(): \Illuminate\Support\Collection
    {
        $centro = $this->centroDelSupervisor();

        return $centro === null ? collect() : app(ConfiguracionEfectivaCentro::class)->prestaciones($centro)->sortBy('nombre')->values();
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
            return new Collection;
        }

        return SesionActividad::where('actividad_id', $this->actividadIdSesiones)
            ->with('sala', 'profesionales.cargo', 'actividad')
            ->withCount(['inscripciones as inscritas_count' => fn ($q) => $q->vigentes()])
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();
    }

    /**
     * Sesión cuyos inscritos se muestran, con la comprobación de la policy.
     *
     * @return SesionActividad|null
     */
    #[Computed]
    public function sesionInscritos(): ?SesionActividad
    {
        if ($this->inscritosSesionId === null) {
            return null;
        }

        $sesion = SesionActividad::where('id', $this->inscritosSesionId)
            ->where('actividad_id', $this->actividadIdSesiones)
            ->firstOrFail();

        Gate::authorize('verInscritos', [InscripcionSesion::class, $sesion]);

        return $sesion;
    }

    /**
     * Inscripciones vigentes de la sesión, con la persona presentada según RN-17: la
     * identidad de una persona protegida solo para quien la puede ver.
     *
     * @return list<array{persona: array{visible: bool, id: int|null, nombre: string, url: string|null, ciudadano: mixed}, via: ViaInscripcion, inscrita_por: string, fecha: string}>
     */
    #[Computed]
    public function inscritos(): array
    {
        $sesion = $this->sesionInscritos;

        if ($sesion === null) {
            return [];
        }

        $usuario = auth()->user();

        return $sesion->inscripciones()->vigentes()->with('ciudadano', 'inscritaPor')->orderBy('created_at')->orderBy('id')->get()
            ->map(fn (InscripcionSesion $i) => [
                'persona' => PresentacionPersona::paraListado($i->ciudadano, $usuario),
                'via' => $i->via,
                'inscrita_por' => $i->inscritaPor?->nombreVisible() ?? '—',
                'fecha' => $i->created_at->format('d/m/Y H:i'),
            ])->all();
    }

    /**
     * Aforo ocupado y total de la sesión por vía (null = sin límite). Con el aforo compartido,
     * el total es el mismo para las dos vías.
     *
     * @return list<array{via: ViaInscripcion, ocupadas: int, total: int|null}>
     */
    #[Computed]
    public function ocupacionInscritos(): array
    {
        $sesion = $this->sesionInscritos;

        if ($sesion === null) {
            return [];
        }

        $vias = array_filter(ViaInscripcion::cases(), fn (ViaInscripcion $via) => $via->admitidaEn($sesion->actividad->modo_acceso));

        return array_values(array_map(function (ViaInscripcion $via) use ($sesion) {
            return ['via' => $via, 'ocupadas' => $sesion->ocupadas($via), 'total' => $sesion->capacidad($via)];
        }, $vias));
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
            return new Collection;
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
            'aforoTotal', 'aforoPresc', 'requiereInscripcion', 'prestacionId',
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
     *
     * @return void
     */
    public function abrirEdicion(int $id): void
    {
        $centro = $this->centroDelSupervisor();

        $actividad = Actividad::where('id', $id)
            ->when($centro !== null, fn ($q) => $q->where('centro_id', $centro->id))
            ->firstOrFail();

        $this->editandoId = $actividad->id;
        $this->nombre = $actividad->nombre;
        $this->tipoActividadId = $actividad->tipo_actividad_id;
        $this->modoAcceso = $actividad->modo_acceso;
        $this->aforoTotal = $actividad->aforo_total;
        $this->aforoPresc = $actividad->aforo_prescripcion;
        $this->fechaAlta = $actividad->fecha_alta->toDateString();
        $this->requiereInscripcion = (bool) $actividad->requiere_inscripcion_centro;
        $this->prestacionId = $actividad->prestacion_id;
        $this->profesionalesIds = $actividad->profesionales()->get()->pluck('id')->map(fn ($v) => (int) $v)->toArray();
        $this->busquedaProfesional = '';
        $this->buscarEnTodo = false;
        $this->modalAbierto = true;
    }

    /**
     * Añade el profesional indicado a la lista de la actividad.
     *
     * @param int $profesionalId
     *
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
     *
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
                'nombre' => ['required', 'string', 'max:200'],
                'tipoActividadId' => ['required', 'integer', 'exists:tipos_actividad,id'],
                'modoAcceso' => ['required', 'in:libre,prescripcion,mixta'],
                'aforoTotal' => ['nullable', 'integer', 'min:1'],
                'aforoPresc' => ['nullable', 'integer', 'min:0'],
                'fechaAlta' => ['required', 'date'],
                'prestacionId' => ['nullable', 'integer', 'in:'.$this->prestacionesDelCentro->pluck('id')->implode(',')],
                'profesionalesIds' => ['required', 'array', 'min:1'],
                'profesionalesIds.*' => ['integer', 'exists:profesionales,id'],
            ],
            [
                'profesionalesIds.required' => 'Debes asignar al menos un profesional responsable.',
                'profesionalesIds.min' => 'Debes asignar al menos un profesional responsable.',
                'prestacionId.in' => 'El centro no ofrece esa prestación.',
            ]
        );

        $datos = [
            'tipo_actividad_id' => $this->tipoActividadId,
            'nombre' => trim($this->nombre),
            'modo_acceso' => $this->modoAcceso,
            'aforo_total' => $this->aforoTotal,
            'aforo_prescripcion' => $this->modoAcceso !== 'libre' ? $this->aforoPresc : null,
            'requiere_inscripcion_centro' => $this->requiereInscripcion,
            'prestacion_id' => $this->prestacionId,
            'fecha_alta' => $this->fechaAlta,
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
                'activa' => true,
            ]));
        }

        $actividad->profesionales()->sync($this->profesionalesIds);

        $this->modalAbierto = false;
        unset($this->actividades);
    }

    // =========================================================================
    // Acciones — baja de actividad (F6-5)
    // =========================================================================

    /**
     * Abre la confirmación de baja de una actividad del centro.
     *
     * @param int $id
     *
     * @return void
     */
    public function abrirBaja(int $id): void
    {
        $this->bajaActividadId = $this->actividadDelCentro($id)->id;
        $this->motivoBaja = '';
        $this->resetErrorBag('motivoBaja');
    }

    /**
     * Da de baja la actividad: deja de admitir inscripciones y sus sesiones futuras se cancelan.
     *
     * @return void
     */
    public function confirmarBaja(): void
    {
        if ($this->bajaActividadId === null) {
            return;
        }

        $this->resetErrorBag('motivoBaja');

        try {
            app(CicloVidaActividadService::class)->darDeBajaActividad($this->actividadDelCentro($this->bajaActividadId), $this->motivoBaja, auth()->user());
        } catch (DomainException|AuthorizationException $e) {
            $this->addError('motivoBaja', $e->getMessage());

            return;
        }

        $this->bajaActividadId = null;
        $this->motivoBaja = '';
        unset($this->actividades);
    }

    /**
     * Muestra u oculta las actividades dadas de baja.
     *
     * @return void
     */
    public function updatedVerBajas(): void
    {
        unset($this->actividades);
    }

    // =========================================================================
    // Acciones — modal sesiones
    // =========================================================================

    /**
     * Abre el modal de sesiones para la actividad indicada en modo lista.
     *
     * @param int $actividadId
     *
     * @return void
     */
    public function abrirSesiones(int $actividadId): void
    {
        $this->actividadIdSesiones = $actividadId;
        $this->sesionesModo = 'lista';
        $this->cancelandoSesionId = null;
        $this->inscritosSesionId = null;
        $this->resetErrorBag();
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
        $this->sesionFecha = Carbon::today()->toDateString();
        $this->sesionHoraInicio = '09:00';
        $this->sesionesModo = 'formulario';
    }

    /**
     * Carga los datos de la sesión indicada y abre el formulario en modo edición.
     *
     * @param int $sesionId
     *
     * @return void
     */
    public function editarSesion(int $sesionId): void
    {
        $sesion = SesionActividad::where('id', $sesionId)
            ->where('actividad_id', $this->actividadIdSesiones)
            ->firstOrFail();

        $this->editandoSesionId = $sesion->id;
        $this->sesionFecha = $sesion->fecha->toDateString();
        $this->sesionHoraInicio = substr((string) $sesion->hora_inicio, 0, 5);
        $this->sesionHoraFin = $sesion->hora_fin ? substr((string) $sesion->hora_fin, 0, 5) : '';
        $this->sesionSalaId = $sesion->sala_id;
        $this->sesionAforoTotal = $sesion->aforo_total;
        $this->sesionAforoPresc = $sesion->aforo_prescripcion;
        $this->sesionNotas = $sesion->notas ?? '';
        $this->sesionProfesionalesIds = $sesion->profesionales()->get()->pluck('id')->map(fn ($v) => (int) $v)->toArray();
        $this->sesionBusquedaProfesional = '';
        $this->sesionBuscarEnTodo = false;
        $this->sesionesModo = 'formulario';
    }

    /**
     * Guarda el formulario de sesión (crea o actualiza) y sincroniza profesionales.
     *
     * @return void
     */
    public function guardarSesion(): void
    {
        $this->validate([
            'sesionFecha' => ['required', 'date'],
            'sesionHoraInicio' => ['required', 'date_format:H:i'],
            'sesionHoraFin' => ['nullable', 'date_format:H:i', 'after:sesionHoraInicio'],
            'sesionSalaId' => ['nullable', 'integer', 'exists:salas,id'],
            'sesionAforoTotal' => ['nullable', 'integer', 'min:1'],
            'sesionAforoPresc' => ['nullable', 'integer', 'min:0'],
            'sesionNotas' => ['nullable', 'string', 'max:1000'],
            'sesionProfesionalesIds.*' => ['integer', 'exists:profesionales,id'],
        ]);

        $datos = [
            'actividad_id' => $this->actividadIdSesiones,
            'fecha' => $this->sesionFecha,
            'hora_inicio' => $this->sesionHoraInicio,
            'hora_fin' => $this->sesionHoraFin ?: null,
            'sala_id' => $this->sesionSalaId,
            'aforo_total' => $this->sesionAforoTotal,
            'aforo_prescripcion' => $this->sesionAforoPresc,
            'notas' => filled($this->sesionNotas) ? trim($this->sesionNotas) : null,
        ];

        if ($this->editandoSesionId !== null) {
            $sesion = SesionActividad::where('id', $this->editandoSesionId)
                ->where('actividad_id', $this->actividadIdSesiones)
                ->firstOrFail();

            // El aforo no baja de las plazas ya ocupadas en ninguna vía
            $prueba = (clone $sesion)->fill($datos);
            foreach (ViaInscripcion::cases() as $via) {
                $capacidad = $prueba->capacidad($via);
                $ocupadas = $prueba->ocupadasContra($via);
                if ($via->admitidaEn($sesion->actividad->modo_acceso) && $capacidad !== null && $ocupadas > $capacidad) {
                    $this->addError('sesionAforoTotal', "Ya hay {$ocupadas} inscripciones que ocupan ese aforo ({$via->label()}): no puede ser menor.");

                    return;
                }
            }

            $sesion->update($datos);
        } else {
            // El estado no se elige: nace programada y cambia por las acciones (F6-5)
            $sesion = SesionActividad::create($datos + ['estado' => 'programada']);
        }

        $sesion->profesionales()->sync($this->sesionProfesionalesIds);

        $this->sesionesModo = 'lista';
        unset($this->sesiones);
    }

    /**
     * Elimina la sesión indicada si no tiene inscripciones ni prescripciones (F6-5).
     *
     * @param int $sesionId
     *
     * @return void
     */
    public function eliminarSesion(int $sesionId): void
    {
        $this->accionSesion($sesionId, fn (SesionActividad $sesion, CicloVidaActividadService $ciclo) => $ciclo->eliminarSesion($sesion, auth()->user()));
    }

    /**
     * Abre el formulario de motivo para cancelar una sesión.
     *
     * @param int $sesionId
     *
     * @return void
     */
    public function abrirCancelacion(int $sesionId): void
    {
        $this->cancelandoSesionId = $this->sesionDeLaActividad($sesionId)->id;
        $this->motivoCancelacion = '';
        $this->resetErrorBag();
    }

    /**
     * Cancela la sesión: sus inscripciones se dan de baja y se avisa a quien las hizo.
     *
     * @return void
     */
    public function confirmarCancelacion(): void
    {
        if ($this->cancelandoSesionId === null) {
            return;
        }

        $hecho = $this->accionSesion($this->cancelandoSesionId, fn (SesionActividad $sesion, CicloVidaActividadService $ciclo) => $ciclo->cancelarSesion($sesion, $this->motivoCancelacion, auth()->user()));

        if ($hecho) {
            $this->cancelandoSesionId = null;
            $this->motivoCancelacion = '';
        }
    }

    /**
     * Marca como celebrada una sesión que ya ha empezado (sin asistencia, F6-6).
     *
     * @param int $sesionId
     *
     * @return void
     */
    public function marcarCelebrada(int $sesionId): void
    {
        $this->accionSesion($sesionId, fn (SesionActividad $sesion, CicloVidaActividadService $ciclo) => $ciclo->marcarCelebrada($sesion, auth()->user()));
    }

    /**
     * Muestra los inscritos de una sesión.
     *
     * @param int $sesionId
     *
     * @return void
     */
    public function verInscritos(int $sesionId): void
    {
        $this->inscritosSesionId = $this->sesionDeLaActividad($sesionId)->id;
        unset($this->sesionInscritos, $this->inscritos, $this->ocupacionInscritos);
        $this->sesionesModo = 'inscritos';
    }

    /**
     * Vuelve a la vista de lista dentro del modal de sesiones.
     *
     * @return void
     */
    public function volverALista(): void
    {
        $this->sesionesModo = 'lista';
        $this->inscritosSesionId = null;
    }

    /**
     * Añade el profesional indicado a la lista de la sesión.
     *
     * @param int $profesionalId
     *
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
     *
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
     * @param bool $todoOrg Si true, busca en toda la organización.
     * @param string $busqueda Texto libre (mín. 2 chars en modo org.).
     *
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
            return new Collection;
        }

        $termino = '%'.trim($busqueda).'%';

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
     *
     * @return Collection<int, Profesional>
     */
    private function profesionalesPorIds(array $ids): Collection
    {
        if (empty($ids)) {
            return new Collection;
        }

        return Profesional::whereIn('id', $ids)
            ->with('cargo')
            ->orderBy('apellido1')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Ejecuta una acción del ciclo de vida sobre una sesión de la actividad abierta y
     * muestra el rechazo del servicio como error de la lista.
     *
     * @param int $sesionId
     * @param callable(SesionActividad, CicloVidaActividadService): void $accion
     *
     * @return bool Si la acción se ha hecho.
     */
    private function accionSesion(int $sesionId, callable $accion): bool
    {
        try {
            $accion($this->sesionDeLaActividad($sesionId), app(CicloVidaActividadService::class));
        } catch (DomainException|AuthorizationException $e) {
            $this->addError('sesiones', $e->getMessage());

            return false;
        }

        $this->resetErrorBag('sesiones');
        unset($this->sesiones);

        return true;
    }

    /**
     * Sesión de la actividad abierta en el modal; 404 si es de otra.
     *
     * @param int $sesionId
     *
     * @return SesionActividad
     */
    private function sesionDeLaActividad(int $sesionId): SesionActividad
    {
        return SesionActividad::where('id', $sesionId)
            ->where('actividad_id', $this->actividadIdSesiones)
            ->firstOrFail();
    }

    /**
     * Actividad del centro supervisado; 404 si es de otro.
     *
     * @param int $id
     *
     * @return Actividad
     */
    private function actividadDelCentro(int $id): Actividad
    {
        $centro = $this->centroDelSupervisor();

        return Actividad::where('id', $id)
            ->where('centro_id', $centro?->id)
            ->firstOrFail();
    }

    /**
     * Resuelve el Centro asociado a la primera UO activa del supervisor autenticado.
     *
     * @return Centro|null
     */
    private function centroDelSupervisor(): ?Centro
    {
        return app(CentroDeUsuario::class)->centroSupervisado(auth()->user());
    }
}
