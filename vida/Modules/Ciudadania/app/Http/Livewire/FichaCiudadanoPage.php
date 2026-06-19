<?php

namespace Modules\Ciudadania\Http\Livewire;

use App\Models\Audit;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use App\Queries\AccesosExpedienteQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Atencion\Models\RegistroAtencion;
use Modules\Ciudadania\Models\CiudadanoIdentificador;
use Modules\Ciudadania\Models\CiudadanoPrestacionResumen;
use Modules\Ciudadania\Models\CiudadanoRelacion;
use Modules\Ciudadania\Models\TipoRelacion;
use Modules\Ciudadania\Models\UnidadConvivencia;
use Modules\Ciudadania\Models\UnidadConvivenciaMiembro;
use Modules\Ciudadania\Services\NormalizadorCiudadano;

/**
 * Ficha del ciudadano: vista y edición de Capa 1 (datos identificativos y de contacto).
 *
 * Distinta de intervencion/ciudadano/{historia}: pivota sobre Ciudadano,
 * no sobre HistoriaSocial. Accesible aunque el ciudadano no tenga historia social.
 *
 * Se accede sin AmbitoUoScope porque un ciudadano puede no tener historia social
 * en ninguna UO y aun así tener ficha (e.g., recién creado vía alta ciudadano).
 *
 * @see docs/instrucciones-cli/instrucciones-cli-ficha-ciudadano.md
 * @see docs/front/ui-ficha-ciudadano.md
 *
 * Propiedades computadas expuestas como propiedades mágicas por Livewire 4 #[Computed]:
 *
 * @property-read bool $puedeEditar
 * @property-read Ciudadano $ciudadano
 * @property-read HistoriaSocial|null $historiaSocial
 * @property-read bool $puedeVerHistoria
 * @property-read Collection<int, CiudadanoIdentificador> $documentos
 * @property-read UnidadConvivencia|null $ucVigente
 * @property-read Collection<int, UnidadConvivenciaMiembro> $ucMiembros
 * @property-read bool $puedeEditarRelaciones
 * @property-read Collection<int, CiudadanoRelacion> $relacionesActivas
 * @property-read Collection<int, CiudadanoRelacion> $relacionesHistoricas
 * @property-read Collection<int, Ciudadano> $relacionResultadosBusqueda
 * @property-read Ciudadano|null $ciudadanoSeleccionadoRelacion
 * @property-read Collection<int, CiudadanoPrestacionResumen> $prestaciones
 * @property-read Collection<int, Audit> $actividadReciente
 * @property-read bool $puedeVerAccesos
 * @property-read bool $puedeVerTodosLosAccesos
 * @property-read bool $puedeAbrirHistoria
 * @property-read bool $puedeCrearAtencion
 * @property-read Collection<int, RegistroAtencion> $historialAtenciones
 */
#[Layout('layouts.operativo')]
class FichaCiudadanoPage extends Component
{
    // -------------------------------------------------------------------------
    // Control de estado
    // -------------------------------------------------------------------------

    /** ID del ciudadano. Se almacena como int para evitar problemas de hidratación con AmbitoUoScope. */
    public int $ciudadanoId;

    /** Activa la edición simultánea de todos los campos de Capa 1. */
    public bool $modoEdicion = false;

    // -------------------------------------------------------------------------
    // Campos editables de Capa 1
    // -------------------------------------------------------------------------

    public string $nombre = '';

    public string $apellido1 = '';

    public string $apellido2 = '';

    public string $fechaNacimiento = '';

    public string $sexo = '';

    public string $alias = '';

    public string $direccionTexto = '';

    public string $telefono = '';

    public string $email = '';

    // -------------------------------------------------------------------------
    // Modal de nueva atención
    // -------------------------------------------------------------------------

    /** Controla la visibilidad del modal de nueva atención. */
    public bool $modalAtencionAbierto = false;

    /** Tipo de atención que se está registrando. */
    public string $atencionTipo = 'informacion';

    /** Fecha de la atención (Y-m-d). */
    public string $atencionFecha = '';

    /** Prestación del catálogo vinculada (opcional). */
    public ?int $atencionPrestacionId = null;

    /** Demanda expresada por el ciudadano. */
    public string $atencionDemanda = '';

    /** Respuesta o actuación del profesional. */
    public string $atencionRespuesta = '';

    /** Mensaje de confirmación tras guardar. */
    public string $atencionMensaje = '';

    // -------------------------------------------------------------------------
    // Modal de nuevo documento
    // -------------------------------------------------------------------------

    public bool $modalDocumento = false;

    public string $nuevoTipoDocumento = 'nif';

    public string $nuevoValorDocumento = '';

    // -------------------------------------------------------------------------
    // Gestión de relaciones
    // -------------------------------------------------------------------------

    /** Controla la visibilidad del modal de relación. */
    public bool $modalRelacionAbierto = false;

    /** ID de la relación en edición; null cuando se crea una nueva. */
    public ?int $relacionId = null;

    public bool $mostrarHistorialRelaciones = false;

    public string $relacionBusqueda = '';

    public ?int $relacionCiudadanoSeleccionado = null;

    public string $relacionTipo = '';

    public string $relacionFechaInicio = '';

    public string $relacionFechaFin = '';

    public string $relacionObservaciones = '';

    public string $relacionMensaje = '';

    // -------------------------------------------------------------------------
    // Ciclo de vida
    // -------------------------------------------------------------------------

    /**
     * @param int $ciudadano ID del ciudadano (parámetro de ruta {ciudadano})
     */
    public function mount(int $ciudadano): void
    {
        /** @var User $user */
        $user = auth()->user();
        if (! $user->hasAnyRole(['intervencion', 'tramitacion', 'consulta_basica', 'supervision'])) {
            abort(403);
        }

        $c = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->findOrFail($ciudadano);

        $this->ciudadanoId = $c->id;
        $this->nombre = $c->nombre ?? '';
        $this->apellido1 = $c->apellido1 ?? '';
        $this->apellido2 = $c->apellido2 ?? '';
        $this->fechaNacimiento = $c->fecha_nacimiento ?? '';
        $this->sexo = $c->sexo ?? '';
        $this->alias = $c->alias ?? '';
        $this->direccionTexto = $c->direccion_texto ?? '';
        $this->telefono = $c->telefono ?? '';
        $this->email = $c->email ?? '';
    }

    // -------------------------------------------------------------------------
    // Propiedades computadas
    // -------------------------------------------------------------------------

    /**
     * Ciudadano sin AmbitoUoScope — accesible aunque no tenga historia social en la UO.
     */
    #[Computed]
    public function ciudadano(): Ciudadano
    {
        return Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->findOrFail($this->ciudadanoId);
    }

    /**
     * El rol supervision tiene acceso de solo lectura. Todos los demás con acceso pueden editar.
     */
    #[Computed]
    public function puedeEditar(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->hasAnyRole(['intervencion', 'tramitacion', 'consulta_basica']);
    }

    /**
     * Historia social sin AmbitoUoScope ni SoftDeletes — solo comprueba existencia.
     * La historia es única y permanente: nunca se cierra.
     */
    #[Computed]
    public function historiaSocial(): ?HistoriaSocial
    {
        return HistoriaSocial::withoutGlobalScopes()
            ->where('ciudadano_id', $this->ciudadanoId)
            ->first();
    }

    /**
     * Solo el rol intervencion puede navegar a la historia social.
     */
    #[Computed]
    public function puedeVerHistoria(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->hasRole('intervencion');
    }

    /**
     * Historial completo de documentos de identidad, descendente por fecha de inicio.
     *
     * @return Collection<int, CiudadanoIdentificador>
     */
    #[Computed]
    public function documentos(): Collection
    {
        return CiudadanoIdentificador::where('ciudadano_id', $this->ciudadanoId)
            ->orderByDesc('fecha_inicio')
            ->get();
    }

    /**
     * UC vigente del ciudadano (primera con fecha_fin nula o futura).
     * Sin AmbitoUoScope porque la UC no tiene ámbito UO propio.
     */
    #[Computed]
    public function ucVigente(): ?UnidadConvivencia
    {
        $ciudadano = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->find($this->ciudadanoId);
        if (! $ciudadano) {
            return null;
        }

        return $ciudadano->unidadesConvivenciaActivas()->first();
    }

    /**
     * Miembros activos de la UC vigente, enriquecidos con el tipo de relación si existe.
     * Carga el ciudadano conviviente sin AmbitoUoScope porque puede pertenecer a otra UO.
     *
     * @return Collection<int, UnidadConvivenciaMiembro>
     */
    #[Computed]
    public function ucMiembros(): Collection
    {
        if (! $this->ucVigente) {
            return collect();
        }

        $miembros = $this->ucVigente->miembrosActivos()
            ->with(['ciudadano' => fn ($q) => $q->withoutGlobalScope(AmbitoUoScope::class)])
            ->get();

        // Índice de relaciones activas del titular hacia cada conviviente
        $relacionesPorCiudadano = CiudadanoRelacion::where('ciudadano_id', $this->ciudadanoId)
            ->whereNull('fecha_fin')
            ->whereIn('ciudadano_relacionado_id', $miembros->pluck('ciudadano_id'))
            ->with('tipoRelacion')
            ->get()
            ->keyBy('ciudadano_relacionado_id');

        return $miembros->map(function (UnidadConvivenciaMiembro $miembro) use ($relacionesPorCiudadano): UnidadConvivenciaMiembro {
            $miembro->tipo_relacion_etiqueta = $relacionesPorCiudadano->get($miembro->ciudadano_id)
                ?->tipoRelacion?->etiqueta ?? null;

            return $miembro;
        });
    }

    /**
     * Solo los roles con competencia de tramitación o intervención pueden crear o editar relaciones.
     */
    #[Computed]
    public function puedeEditarRelaciones(): bool
    {
        return auth()->user()->hasAnyRole(['intervencion', 'tramitacion']);
    }

    /**
     * Últimas 4 prestaciones ordenadas por estado (activas primero) y fecha.
     * Se leen desde la tabla de agregación — nunca de los módulos origen directamente.
     *
     * @return Collection<int, CiudadanoPrestacionResumen>
     */
    #[Computed]
    public function prestaciones(): Collection
    {
        return CiudadanoPrestacionResumen::where('ciudadano_id', $this->ciudadanoId)
            ->recientes(4)
            ->get();
    }

    /**
     * El panel de accesos es visible solo para roles con competencia de intervención o supervisión.
     * Revelar metadatos de acceso a roles sin competencia es una fuga de información sobre el caso.
     */
    #[Computed]
    public function puedeVerAccesos(): bool
    {
        return auth()->user()->hasAnyRole(['adm_sistema', 'supervision', 'intervencion']);
    }

    /**
     * Indica si el usuario ve todos los accesos (TSR/supervisor/adm) o solo los propios.
     * Usado en la vista para mostrar u ocultar el enlace "Ver todo".
     */
    #[Computed]
    public function puedeVerTodosLosAccesos(): bool
    {
        $historia = $this->historiaSocial;
        if (! $historia) {
            return false;
        }

        return app(AccesosExpedienteQuery::class)->puedeVerTodos(auth()->user(), $historia);
    }

    /**
     * Últimos 10 accesos al expediente de este ciudadano.
     *
     * Visibilidad por rol (spec §5):
     *   - adm_sistema → todos los accesos.
     *   - TSR responsable del plan activo → todos los accesos.
     *   - Supervisor con la UO de la historia en su árbol → todos los accesos.
     *   - Cualquier otro profesional → solo sus propios accesos.
     *
     * La restricción es deliberada: revelar quién accedió al expediente
     * es en sí mismo una fuga de metadatos sobre el estado del caso.
     *
     * @return Collection<int, Audit>
     */
    #[Computed]
    public function actividadReciente(): Collection
    {
        if (! $this->puedeVerAccesos) {
            return collect();
        }

        $historia = $this->historiaSocial;
        if (! $historia) {
            return collect();
        }

        return app(AccesosExpedienteQuery::class)
            ->paraUsuario(auth()->user(), $this->ciudadano, $historia)
            ->limit(10)
            ->get();
    }

    /**
     * Tipos de relación activos disponibles para crear nuevas relaciones.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function tiposRelacion(): array
    {
        return TipoRelacion::opcionesParaSelect();
    }

    /**
     * Relaciones activas salientes desde este ciudadano, con persona y tipo cargados.
     * El eager load de ciudadanoRelacionado bypasea AmbitoUoScope: el ciudadano
     * relacionado puede pertenecer a cualquier UO.
     *
     * @return Collection<int, CiudadanoRelacion>
     */
    #[Computed]
    public function relacionesActivas(): Collection
    {
        return CiudadanoRelacion::where('ciudadano_id', $this->ciudadanoId)
            ->whereNull('fecha_fin')
            ->with([
                'ciudadanoRelacionado' => fn ($q) => $q->withoutGlobalScope(AmbitoUoScope::class),
                'tipoRelacion',
            ])
            ->orderByDesc('fecha_inicio')
            ->get();
    }

    /**
     * Historial completo de relaciones del ciudadano (vigentes y cerradas).
     *
     * @return Collection<int, CiudadanoRelacion>
     */
    #[Computed]
    public function relacionesHistoricas(): Collection
    {
        return CiudadanoRelacion::where('ciudadano_id', $this->ciudadanoId)
            ->with([
                'ciudadanoRelacionado' => fn ($q) => $q->withoutGlobalScope(AmbitoUoScope::class),
                'tipoRelacion',
            ])
            ->orderByRaw('fecha_fin is null desc')
            ->orderByDesc('fecha_inicio')
            ->get();
    }

    /**
     * Resultados de búsqueda para añadir una relación. Los nombres están cifrados,
     * por eso se filtra en memoria siguiendo el patrón del buscador de ciudadanos.
     *
     * @return Collection<int, Ciudadano>
     */
    #[Computed]
    public function relacionResultadosBusqueda(): Collection
    {
        if (strlen(trim($this->relacionBusqueda)) < 2) {
            return collect();
        }

        return Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
            ->where('id', '!=', $this->ciudadanoId)
            ->limit(500)
            ->get()
            ->filter(fn (Ciudadano $ciudadano) => str_contains(
                mb_strtolower($ciudadano->nombre_completo),
                mb_strtolower(trim($this->relacionBusqueda))
            ))
            ->take(8)
            ->values();
    }

    /**
     * Ciudadano seleccionado actualmente para la relación.
     */
    #[Computed]
    public function ciudadanoSeleccionadoRelacion(): ?Ciudadano
    {
        if (! $this->relacionCiudadanoSeleccionado) {
            return null;
        }

        return Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
            ->find($this->relacionCiudadanoSeleccionado);
    }

    // -------------------------------------------------------------------------
    // Edición de Capa 1
    // -------------------------------------------------------------------------

    /**
     * Activa el modo edición simultáneo de todos los campos de Capa 1.
     * Solo si puedeEditar — supervision no puede modificar datos.
     */
    public function activarEdicion(): void
    {
        if (! $this->puedeEditar) {
            return;
        }
        $this->modoEdicion = true;
    }

    /**
     * Cancela la edición y recarga los datos desde BD.
     */
    public function cancelarEdicion(): void
    {
        $c = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->findOrFail($this->ciudadanoId);

        $this->nombre = $c->nombre ?? '';
        $this->apellido1 = $c->apellido1 ?? '';
        $this->apellido2 = $c->apellido2 ?? '';
        $this->fechaNacimiento = $c->fecha_nacimiento ?? '';
        $this->sexo = $c->sexo ?? '';
        $this->alias = $c->alias ?? '';
        $this->direccionTexto = $c->direccion_texto ?? '';
        $this->telefono = $c->telefono ?? '';
        $this->email = $c->email ?? '';
        $this->modoEdicion = false;
    }

    /**
     * Valida, normaliza y persiste los campos de Capa 1.
     * Solo si puedeEditar. DireccionObserver procesará geocodificación si cambia direccion_texto.
     *
     *
     * @throws ValidationException
     */
    public function guardar(): void
    {
        if (! $this->puedeEditar) {
            return;
        }

        $this->validate([
            'nombre' => 'required|string|max:100',
            'apellido1' => 'required|string|max:100',
            'apellido2' => 'nullable|string|max:100',
            'fechaNacimiento' => 'nullable|date|before:today',
            'sexo' => 'required|string',
            'alias' => 'nullable|string|max:200',
            'direccionTexto' => 'nullable|string|max:500',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ]);

        $norm = NormalizadorCiudadano::normalizar([
            'nombre' => $this->nombre,
            'apellido1' => $this->apellido1,
            'apellido2' => $this->apellido2,
            'telefono' => $this->telefono,
            'email' => $this->email,
        ]);

        Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
            ->findOrFail($this->ciudadanoId)
            ->update([
                'nombre' => $norm['nombre'] ?? $this->nombre,
                'apellido1' => $norm['apellido1'] ?? $this->apellido1,
                'apellido2' => $norm['apellido2'] ?? ($this->apellido2 ?: null),
                'fecha_nacimiento' => $this->fechaNacimiento ?: null,
                'sexo' => $this->sexo,
                'alias' => $this->alias ?: null,
                'direccion_texto' => $this->direccionTexto ?: null,
                'telefono' => $norm['telefono'] ?? ($this->telefono ?: null),
                'email' => $norm['email'] ?? ($this->email ?: null),
            ]);

        $this->modoEdicion = false;
        $this->dispatch('ciudadano-actualizado');
    }

    // -------------------------------------------------------------------------
    // Gestión de relaciones
    // -------------------------------------------------------------------------

    /**
     * Abre el modal para crear una nueva relación.
     */
    public function abrirModalNuevaRelacion(): void
    {
        if (! $this->puedeEditarRelaciones) {
            return;
        }

        $this->relacionId = null;
        $this->relacionTipo = '';
        $this->relacionCiudadanoSeleccionado = null;
        $this->relacionBusqueda = '';
        $this->relacionFechaInicio = today()->toDateString();
        $this->relacionFechaFin = '';
        $this->relacionObservaciones = '';
        $this->modalRelacionAbierto = true;
    }

    /**
     * Abre el modal con los datos de una relación existente para edición.
     *
     * @param int $relacionId ID de la relación a editar.
     */
    public function abrirModalEditarRelacion(int $relacionId): void
    {
        if (! $this->puedeEditarRelaciones) {
            return;
        }

        $relacion = CiudadanoRelacion::where('ciudadano_id', $this->ciudadanoId)
            ->where('id', $relacionId)
            ->first();

        if (! $relacion) {
            return;
        }

        $this->relacionId = $relacionId;
        $this->relacionTipo = $relacion->tipo_relacion;
        $this->relacionCiudadanoSeleccionado = $relacion->ciudadano_relacionado_id;
        $this->relacionBusqueda = '';
        $this->relacionFechaInicio = $relacion->fecha_inicio?->toDateString() ?? today()->toDateString();
        $this->relacionFechaFin = $relacion->fecha_fin?->toDateString() ?? '';
        $this->relacionObservaciones = $relacion->observaciones ?? '';
        $this->modalRelacionAbierto = true;
    }

    /**
     * Cierra el modal y limpia el estado del formulario de relación.
     */
    public function cerrarModalRelacion(): void
    {
        $this->modalRelacionAbierto = false;
        $this->relacionId = null;
        $this->relacionTipo = '';
        $this->relacionCiudadanoSeleccionado = null;
        $this->relacionBusqueda = '';
        $this->relacionFechaInicio = '';
        $this->relacionFechaFin = '';
        $this->relacionObservaciones = '';
    }

    /**
     * Alterna la visibilidad del historial de relaciones cerradas.
     */
    public function toggleHistorialRelaciones(): void
    {
        $this->mostrarHistorialRelaciones = ! $this->mostrarHistorialRelaciones;
    }

    /**
     * Registra el ciudadano seleccionado en el buscador del modal de relación.
     *
     * @param int $ciudadanoId ID del ciudadano relacionado.
     */
    public function seleccionarCiudadanoRelacion(int $ciudadanoId): void
    {
        if (! $this->puedeEditarRelaciones || $ciudadanoId === $this->ciudadanoId) {
            return;
        }

        $this->relacionCiudadanoSeleccionado = $ciudadanoId;
        $this->relacionBusqueda = '';
    }

    /**
     * Crea o actualiza una relación entre ciudadanos.
     * Si $relacionId es null, crea; si es int, actualiza solo las observaciones.
     * Requiere permiso de tramitación o intervención; aborta con 403 si no.
     *
     *
     * @throws ValidationException
     */
    public function guardarRelacion(): void
    {
        if (! $this->puedeEditarRelaciones) {
            abort(403);
        }

        if ($this->relacionId !== null) {
            // Modo edición: solo se pueden modificar observaciones
            $this->validate([
                'relacionObservaciones' => 'nullable|string|max:1000',
            ]);

            $relacion = CiudadanoRelacion::where('ciudadano_id', $this->ciudadanoId)
                ->where('id', $this->relacionId)
                ->firstOrFail();

            $relacion->update(['observaciones' => $this->relacionObservaciones ?: null]);
            $this->relacionMensaje = 'Relación actualizada correctamente.';
        } else {
            // Modo creación
            $this->validate([
                'relacionCiudadanoSeleccionado' => 'required|integer|exists:ciudadanos,id|different:ciudadanoId',
                'relacionTipo' => 'required|string|exists:tipos_relacion,slug',
                'relacionFechaInicio' => 'required|date',
                'relacionObservaciones' => 'nullable|string|max:1000',
            ]);

            if (! TipoRelacion::activos()->where('slug', $this->relacionTipo)->exists()) {
                throw ValidationException::withMessages([
                    'relacionTipo' => 'El tipo de relación seleccionado no está activo.',
                ]);
            }

            $duplicada = CiudadanoRelacion::where('ciudadano_id', $this->ciudadanoId)
                ->where('ciudadano_relacionado_id', $this->relacionCiudadanoSeleccionado)
                ->where('tipo_relacion', $this->relacionTipo)
                ->whereNull('fecha_fin')
                ->exists();

            if ($duplicada) {
                throw ValidationException::withMessages([
                    'relacionTipo' => 'Ya existe una relación activa de ese tipo con esta persona.',
                ]);
            }

            CiudadanoRelacion::create([
                'ciudadano_id' => $this->ciudadanoId,
                'ciudadano_relacionado_id' => $this->relacionCiudadanoSeleccionado,
                'tipo_relacion' => $this->relacionTipo,
                'fecha_inicio' => $this->relacionFechaInicio,
                'observaciones' => $this->relacionObservaciones ?: null,
            ]);

            $this->relacionMensaje = 'Relación añadida correctamente.';
        }

        $this->cerrarModalRelacion();
        unset($this->relacionesActivas, $this->relacionesHistoricas, $this->ucMiembros);
    }

    /**
     * Cierra una relación vigente estableciendo fecha_fin = hoy.
     * El modelo propaga el cierre al registro recíproco automáticamente.
     * Requiere permiso de tramitación o intervención; aborta con 403 si no.
     *
     * @param int $relacionId ID de la relación a cerrar.
     */
    public function cerrarRelacion(int $relacionId): void
    {
        if (! $this->puedeEditarRelaciones) {
            abort(403);
        }

        $relacion = CiudadanoRelacion::where('ciudadano_id', $this->ciudadanoId)
            ->where('id', $relacionId)
            ->whereNull('fecha_fin')
            ->first();

        if (! $relacion) {
            return;
        }

        $relacion->update(['fecha_fin' => today()->toDateString()]);
        $this->cerrarModalRelacion();
        $this->relacionMensaje = 'Relación cerrada correctamente.';
        unset($this->relacionesActivas, $this->relacionesHistoricas, $this->ucMiembros);
    }

    // -------------------------------------------------------------------------
    // Documentos de identidad
    // -------------------------------------------------------------------------

    /**
     * Abre el modal de añadir documento. Solo si puedeEditar.
     */
    public function abrirModalDocumento(): void
    {
        if (! $this->puedeEditar) {
            return;
        }
        $this->modalDocumento = true;
    }

    /**
     * Cierra el modal y limpia el formulario.
     */
    public function cerrarModalDocumento(): void
    {
        $this->nuevoTipoDocumento = 'nif';
        $this->nuevoValorDocumento = '';
        $this->modalDocumento = false;
    }

    /**
     * Cierra el documento activo anterior y crea el nuevo.
     * El historial se mantiene íntegro (principio 4.2 — el pasado es inmutable):
     * los documentos anteriores reciben fecha_fin pero no se eliminan.
     *
     *
     * @throws ValidationException
     */
    public function guardarDocumento(): void
    {
        if (! $this->puedeEditar) {
            return;
        }

        $this->validate([
            'nuevoTipoDocumento' => 'required|in:nif,nie,pasaporte',
            'nuevoValorDocumento' => 'required|string|max:20',
        ]);

        DB::transaction(function (): void {
            CiudadanoIdentificador::where('ciudadano_id', $this->ciudadanoId)
                ->whereNull('fecha_fin')
                ->update(['fecha_fin' => today()]);

            CiudadanoIdentificador::create([
                'ciudadano_id' => $this->ciudadanoId,
                'tipo' => $this->nuevoTipoDocumento,
                'valor' => $this->nuevoValorDocumento,
                'fecha_inicio' => today()->toDateString(),
                'verificado' => false,
                'fuente' => 'manual',
            ]);
        });

        $this->cerrarModalDocumento();
    }

    // -------------------------------------------------------------------------
    // Propiedades computadas — Atención e Historia Social
    // -------------------------------------------------------------------------

    /**
     * Colección de atenciones del ciudadano, ordenadas por fecha descendente.
     *
     * @return Collection<int, RegistroAtencion>
     */
    #[Computed]
    public function historialAtenciones(): Collection
    {
        return $this->ciudadano
            ->registrosAtencion()
            ->with(['profesional', 'prestacion'])
            ->get();
    }

    /**
     * Indica si el usuario puede abrir la Historia Social del ciudadano.
     * Solo cuando el ciudadano no tiene historia y el usuario tiene el permiso.
     */
    #[Computed]
    public function puedeAbrirHistoria(): bool
    {
        return auth()->user()->can('historia.abrir') && ! $this->historiaSocial;
    }

    /**
     * Indica si el usuario puede registrar una nueva atención.
     */
    #[Computed]
    public function puedeCrearAtencion(): bool
    {
        return auth()->user()->can('atencion.crear');
    }

    // -------------------------------------------------------------------------
    // Acciones — Atención e Historia Social
    // -------------------------------------------------------------------------

    /**
     * Crea la Historia Social del ciudadano y redirige a la pantalla de intervención.
     * Solo ejecutable si el ciudadano no tiene historia social previa.
     */
    public function abrirHistoriaSocial(): void
    {
        $this->authorize('create', HistoriaSocial::class);

        if ($this->historiaSocial) {
            return;
        }

        $uoActiva = auth()->user()->uosActivas()->first();

        $historia = HistoriaSocial::create([
            'ciudadano_id' => $this->ciudadanoId,
            'unidad_organizativa_id' => $uoActiva?->id,
            'estado' => 'abierta',
        ]);

        $this->redirect(route('intervencion.ciudadano.show', $historia->id), navigate: true);
    }

    /**
     * Abre el modal de nueva atención con los valores por defecto.
     */
    public function abrirModalAtencion(): void
    {
        $this->modalAtencionAbierto = true;
        $this->atencionFecha = now()->toDateString();
        $this->atencionTipo = 'informacion';
        $this->atencionDemanda = '';
        $this->atencionRespuesta = '';
        $this->atencionPrestacionId = null;
        $this->atencionMensaje = '';
    }

    /**
     * Cierra el modal de nueva atención.
     */
    public function cerrarModalAtencion(): void
    {
        $this->modalAtencionAbierto = false;
    }

    /**
     * Valida y persiste el nuevo registro de atención.
     * Invalida el computed del historial para que se recargue.
     */
    public function guardarAtencion(): void
    {
        $this->authorize('create', RegistroAtencion::class);

        $this->validate([
            'atencionFecha' => 'required|date|before_or_equal:today',
            'atencionDemanda' => 'required|string|min:5|max:2000',
            'atencionRespuesta' => 'nullable|string|max:2000',
        ], [
            'atencionFecha.required' => 'La fecha es obligatoria.',
            'atencionFecha.before_or_equal' => 'La fecha no puede ser futura.',
            'atencionDemanda.required' => 'La demanda es obligatoria.',
            'atencionDemanda.min' => 'Describe la demanda con al menos 5 caracteres.',
        ]);

        RegistroAtencion::create([
            'ciudadano_id' => $this->ciudadanoId,
            'tipo' => $this->atencionTipo,
            'fecha' => $this->atencionFecha,
            'profesional_id' => auth()->id(),
            'prestacion_id' => $this->atencionPrestacionId,
            'demanda' => $this->atencionDemanda,
            'respuesta' => $this->atencionRespuesta,
            'origen' => 'manual',
        ]);

        $this->atencionMensaje = 'Atención registrada correctamente.';
        $this->modalAtencionAbierto = false;
        unset($this->historialAtenciones);
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    /**
     * Renderiza la ficha del ciudadano.
     */
    public function render(): View
    {
        return view('ciudadania::livewire.ficha-ciudadano-page');
    }
}
