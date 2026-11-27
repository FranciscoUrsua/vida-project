<?php

namespace Modules\Ciudadania\Http\Livewire;

use App\Models\Audit;
use App\Models\CatalogoSistema;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use App\Queries\AccesosExpedienteQuery;
use App\Services\AccesoExpediente;
use App\Services\EnlacePersona;
use App\Services\Geocodificacion\Callejero\SeleccionDomicilio;
use App\Services\PresentacionPersona;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Services\Citas\AtencionCitaService;
use Modules\Atencion\Models\RegistroAtencion;
use Modules\Centro\Models\AsignacionCentro;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\Asignacion\AsignacionCentroService;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;
use Modules\Ciudadania\Models\CiudadanoIdentificador;
use Modules\Ciudadania\Models\CiudadanoPrestacionResumen;
use Modules\Ciudadania\Models\CiudadanoRelacion;
use Modules\Ciudadania\Models\TipoRelacion;
use Modules\Ciudadania\Models\UnidadConvivencia;
use Modules\Ciudadania\Models\UnidadConvivenciaMiembro;
use Modules\Ciudadania\Services\NormalizadorCiudadano;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Services\AperturaHistoriaService;
use Modules\Intervencion\Services\Asignacion\AsignacionReferenciaService;

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
 * @property-read array<string, string> $opcionesSexo
 * @property-read Ciudadano $ciudadano
 * @property-read HistoriaSocial|null $historiaSocial
 * @property-read bool $puedeVerHistoria
 * @property-read bool $puedeVerDetalleHistoria
 * @property-read bool $puedeVerEstadoHistoria
 * @property-read array{estado: string, referencia: string|null}|null $estadoHistoria
 * @property-read bool $puedeVerPrestaciones
 * @property-read bool $soloPorEncargo
 * @property-read bool $puedeVerHistorialCitas
 * @property-read bool $puedeVerFichasPersona
 * @property-read Collection<int, AsignacionCentro> $asignacionesCentro
 * @property-read Centro|null $centroParaAsignar
 * @property-read Collection<int, AsignacionProfesional> $asignacionesReferencia
 * @property-read Collection<int, CiudadanoIdentificador> $documentos
 * @property-read UnidadConvivencia|null $ucVigente
 * @property-read Collection<int, UnidadConvivenciaMiembro> $ucMiembros
 * @property-read bool $puedeEditarRelaciones
 * @property-read Collection<int, CiudadanoRelacion> $relacionesActivas
 * @property-read Collection<int, CiudadanoRelacion> $relacionesHistoricas
 * @property-read array<int, array{visible: bool, id: int|null, nombre: string, url: string|null, ciudadano: Ciudadano|null}> $personasRelacionadas
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

    /** @var array<string, mixed> Domicilio en edición (callejero o texto libre; ver SeleccionDomicilio). */
    public array $domicilio = [];

    /** @var array<string, mixed> Domicilio al abrir la edición: si no cambia, no se reescribe. */
    #[Locked]
    public array $domicilioInicial = [];

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

    /** @var int|null Cita con la que se llega desde la agenda (?cita=), para que el registro la complete. */
    #[Url(as: 'cita', except: null)]
    public ?int $citaPedida = null;

    /** @var bool Si el registro de atención se vincula a la cita propuesta (marcado por defecto). */
    public bool $vincularCita = true;

    /** @var int|null Último registro de atención guardado, para dar cita desde él. */
    public ?int $ultimaAtencionId = null;

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
     *
     * @return void
     */
    public function mount(int $ciudadano): void
    {
        /** @var User $user */
        $user = auth()->user();
        $acceso = app(AccesoExpediente::class);

        // El rol se comprueba aquí y no en la ruta para que el intento quede auditado. El
        // profesional de encargo entra por permiso: la policy lo limita a sus personas (EP-6)
        if (! $user->hasAnyRole(['intervencion', 'tramitacion', 'consulta_basica', 'supervision'])
            && ! $user->can('ciudadano.leer_por_encargo')) {
            $acceso->denegarCiudadano($user, $ciudadano);
        }

        // Policy (ámbito y colectivo protegido) y fila de auditoría de la apertura
        $c = $acceso->ciudadano($user, $ciudadano);

        $this->ciudadanoId = $c->id;
        $this->nombre = $c->nombre ?? '';
        $this->apellido1 = $c->apellido1 ?? '';
        $this->apellido2 = $c->apellido2 ?? '';
        $this->fechaNacimiento = $c->fecha_nacimiento ?? '';
        $this->sexo = $c->sexo ?? '';
        $this->alias = $c->alias ?? '';
        $this->direccionTexto = $c->direccion_texto ?? '';
        $this->cargarDomicilio($c);
        $this->telefono = $c->telefono ?? '';
        $this->email = $c->email ?? '';

        // Desde «Atender» en la agenda: el registro de atención se abre ya con la cita propuesta
        if ($this->citaPedida !== null && $this->puedeCrearAtencion && $this->citaVinculable !== null) {
            $this->abrirModalAtencion();
        }
    }

    // -------------------------------------------------------------------------
    // Propiedades computadas
    // -------------------------------------------------------------------------

    /**
     * Ciudadano autorizado por la policy en cada petición (la apertura ya se auditó en mount).
     *
     * @return Ciudadano
     */
    #[Computed]
    public function ciudadano(): Ciudadano
    {
        return app(AccesoExpediente::class)->ciudadano(auth()->user(), $this->ciudadanoId, registrar: false);
    }

    /**
     * Valores admitidos para el sexo, del catálogo `ciudadano.sexo` (clave => etiqueta).
     *
     * @return array<string, string>
     */
    #[Computed]
    public function opcionesSexo(): array
    {
        return CatalogoSistema::opcionesParaSelect('ciudadano.sexo');
    }

    /**
     * Indica si el usuario puede editar los datos de la persona, para ofrecer o no la edición.
     *
     * Delega en CiudadanoPolicy::update, el mismo criterio con el que guardar() autoriza:
     * ámbito de la UO propia (o persona sin historia); fuera de él, solo lectura.
     *
     * @return bool
     */
    #[Computed]
    public function puedeEditar(): bool
    {
        return Gate::allows('update', $this->ciudadano);
    }

    /**
     * Historia social de la persona, para saber si existe y de qué UO es.
     * La historia es única y permanente: nunca se cierra.
     *
     * @return HistoriaSocial|null
     */
    #[Computed]
    public function historiaSocial(): ?HistoriaSocial
    {
        return app(AccesoExpediente::class)->historiaDe(auth()->user(), $this->ciudadanoId);
    }

    /**
     * Asignaciones de centro de la persona: las vigentes primero, después el historial.
     *
     * @return Collection<int, AsignacionCentro>
     */
    #[Computed]
    public function asignacionesCentro(): Collection
    {
        return AsignacionCentro::where('ciudadano_id', $this->ciudadanoId)
            ->with(['centro', 'tipoCentro'])
            ->orderByRaw('fecha_fin is not null')
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->get()
            ->toBase();
    }

    /**
     * Centro al que el usuario puede asignar a la persona desde la ficha («Asignar a este centro»).
     *
     * Solo su centro activo y solo si es de libre elección (el CIAM): registrar que la persona
     * lo elige no contradice RN-04 ni RN-09. Los centros por domicilio siguen pasando por la
     * bandeja del supervisor (decisión del desarrollador, 27 nov 2026). Exige además poder editar
     * a la persona, poder actuar en ese centro (RN-15) y que la persona no tenga ningún centro
     * vigente, que es cuando la ficha dice «Sin centro asignado».
     *
     * @return Centro|null
     */
    #[Computed]
    public function centroParaAsignar(): ?Centro
    {
        /** @var User|null $user */
        $user = auth()->user();

        if ($user === null || $this->soloPorEncargo || ! $this->puedeEditar) {
            return null;
        }

        if ($this->asignacionesCentro->whereNull('fecha_fin')->isNotEmpty()) {
            return null;
        }

        $centros = app(CentroDeUsuario::class);
        $centro = $centros->centroActivo($user);

        if ($centro === null
            || ! $centros->puedeActuar($user, $centro)
            || ! app(ConfiguracionEfectivaCentro::class)->inscripcionLibre($centro)) {
            return null;
        }

        return $centro;
    }

    /**
     * Asigna la persona al centro activo del usuario por libre elección.
     *
     * @return void
     *
     * @throws AuthorizationException Si el usuario no puede asignarla a su centro.
     */
    public function asignarAEsteCentro(): void
    {
        // Se recalcula en el servidor: el botón oculto no basta como control
        unset($this->asignacionesCentro, $this->centroParaAsignar);
        $centro = $this->centroParaAsignar;

        if ($centro === null) {
            throw new AuthorizationException('No puedes asignar a esta persona a tu centro.');
        }

        /** @var User $user */
        $user = auth()->user();
        app(AsignacionCentroService::class)->asignarPorEleccion($this->ciudadano, $centro, $user);

        unset($this->asignacionesCentro, $this->centroParaAsignar);
    }

    /**
     * Refresca el bloque «Centro y referencia» tras un cambio de referencia (fase 3, bloque E).
     *
     * @return void
     */
    #[On('referencia-cambiada')]
    public function refrescarReferencia(): void
    {
        unset($this->asignacionesReferencia);
    }

    /**
     * Profesionales de referencia de la historia: el vigente primero, después el historial.
     *
     * @return Collection<int, AsignacionProfesional>
     */
    #[Computed]
    public function asignacionesReferencia(): Collection
    {
        if ($this->historiaSocial === null) {
            return collect();
        }

        return AsignacionProfesional::where('historia_id', $this->historiaSocial->id)
            ->with('profesional.profesional')
            ->orderByRaw('fecha_fin is not null')
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->get()
            ->toBase();
    }

    /**
     * Si se ofrece el enlace a la historia social.
     *
     * Mira el rol porque reproduce el middleware `role:intervencion` de las rutas de
     * Intervención: con otro rol el enlace llevaría a un 403.
     *
     * @return bool
     */
    #[Computed]
    public function puedeVerHistoria(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->hasRole('intervencion');
    }

    /**
     * Si el usuario ve el detalle de la historia en la ficha: la referencia con su
     * origen, fechas e historial. Sin `historia.leer` (consulta básica, tramitación)
     * solo se ve el estado (fase 4, TF-CBA-02).
     *
     * @return bool
     */
    #[Computed]
    public function puedeVerDetalleHistoria(): bool
    {
        return auth()->user()->can('historia.leer');
    }

    /**
     * Si el usuario ve el bloque «Historia Social» (existencia, estado y referencia).
     *
     * @return bool
     */
    #[Computed]
    public function puedeVerEstadoHistoria(): bool
    {
        return auth()->user()->can('historia.ver_estado');
    }

    /**
     * Estado de la historia y profesional de referencia vigente, o null si no tiene.
     *
     * Solo eso: ni número, ni fechas, ni planes (docs/roles-consulta-basica-tramitacion.md §3.1).
     *
     * @return array{estado: string, referencia: string|null}|null
     */
    #[Computed]
    public function estadoHistoria(): ?array
    {
        $historia = $this->historiaSocial;
        if ($historia === null) {
            return null;
        }

        $referencia = app(AsignacionReferenciaService::class)->vigente($historia);

        return [
            'estado' => $historia->etiquetaEstado(),
            'referencia' => $referencia?->profesional?->nombre_completo,
        ];
    }

    /**
     * Si el usuario solo ve a la persona por sus encargos profesionales (EP-6): ficha en
     * lectura con identificación, contacto, unidad de convivencia y relaciones; nada de
     * centro y referencia, prestaciones, actividades ni carpeta.
     *
     * @return bool
     */
    #[Computed]
    public function soloPorEncargo(): bool
    {
        $usuario = auth()->user();

        return $usuario->can('ciudadano.leer_por_encargo') && ! $usuario->can('ciudadano.leer');
    }

    /**
     * Si el usuario ve el bloque de documentos: la carpeta completa, sus documentos del día o
     * la subida. La supervisión sola no tiene ninguno de esos permisos y no ve documentos (R2).
     * El profesional de encargo trabaja los documentos desde el encargo (bloque D), no aquí.
     *
     * @return bool
     */
    #[Computed]
    public function puedeVerDocumentos(): bool
    {
        $usuario = auth()->user();

        if ($this->soloPorEncargo) {
            return false;
        }

        return $usuario->can('documento.ver_carpeta')
            || $usuario->can('documento.gestionar_propios')
            || $usuario->can('documento.subir');
    }

    /**
     * Si el usuario ve las prestaciones de la persona (bloque «Otras prestaciones»).
     * Consulta básica y tramitación no las ven en la ficha (fase 4, §3.1).
     *
     * @return bool
     */
    #[Computed]
    public function puedeVerPrestaciones(): bool
    {
        return auth()->user()->can('solicitud.ver');
    }

    /**
     * Si el usuario ve las fichas de la persona («Datos sociodemográficos» y demás tipos de uso
     * persona): `FichaPolicy::verDePersona` (permiso, ámbito y personas protegidas, SD-10).
     *
     * @return bool
     */
    #[Computed]
    public function puedeVerFichasPersona(): bool
    {
        return Gate::allows('verDePersona', [Ficha::class, $this->ciudadano]);
    }

    /**
     * Si el usuario ve el historial de citas de la persona (modulo-citas.md §6 bis).
     *
     * @return bool
     */
    #[Computed]
    public function puedeVerHistorialCitas(): bool
    {
        return auth()->user()->can('cita.ver_historial');
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
     *
     * @return UnidadConvivencia|null
     */
    #[Computed]
    public function ucVigente(): ?UnidadConvivencia
    {
        return $this->ciudadano->unidadesConvivenciaActivas()->first();
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
                ?->tipoRelacion->etiqueta;

            return $miembro;
        });
    }

    /**
     * Si el usuario puede crear, editar y cerrar relaciones: `ciudadano.editar_relaciones`
     * y la regla de operación de su UO (CiudadanoPolicy::editarRelaciones).
     *
     * @return bool
     */
    #[Computed]
    public function puedeEditarRelaciones(): bool
    {
        return Gate::allows('editarRelaciones', $this->ciudadano);
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
     * El panel de accesos solo lo ve quien lee la Historia Social (`historia.leer`).
     * Revelar metadatos de acceso a quien no la lee es una fuga de información sobre el caso.
     *
     * @return bool
     */
    #[Computed]
    public function puedeVerAccesos(): bool
    {
        return auth()->user()->can('historia.leer');
    }

    /**
     * Indica si el usuario ve todos los accesos (TSR/supervisor/adm) o solo los propios.
     * Usado en la vista para mostrar u ocultar el enlace "Ver todo".
     *
     * @return bool
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
     * Cómo se muestra cada persona relacionada (relaciones y convivientes), por id.
     *
     * Un colectivo protegido que la policy no deja ver sale como «Persona con
     * protección especial», sin enlace ni datos (CLAUDE.md §3, PresentacionPersona).
     *
     * @return array<int, array{visible: bool, id: int|null, nombre: string, url: string|null, ciudadano: Ciudadano|null}>
     */
    #[Computed]
    public function personasRelacionadas(): array
    {
        /** @var User $usuario */
        $usuario = auth()->user();
        $personas = $this->relacionesHistoricas->map(fn (CiudadanoRelacion $r) => $r->ciudadanoRelacionado)
            ->merge($this->ucMiembros->map(fn (UnidadConvivenciaMiembro $m) => $m->ciudadano))
            ->filter();

        $resultado = [];
        foreach ($personas as $persona) {
            $resultado[$persona->id] ??= PresentacionPersona::paraListado($persona, $usuario);
        }

        return $resultado;
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
            // Colectivos protegidos: quien no puede ver a la persona no la encuentra (CLAUDE.md §3)
            ->reject(fn (Ciudadano $ciudadano) => ! PresentacionPersona::visible($ciudadano, auth()->user()))
            ->take(8)
            ->values();
    }

    /**
     * Ciudadano seleccionado actualmente para la relación.
     *
     * @return Ciudadano|null
     */
    #[Computed]
    public function ciudadanoSeleccionadoRelacion(): ?Ciudadano
    {
        if (! $this->relacionCiudadanoSeleccionado) {
            return null;
        }

        $ciudadano = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
            ->find($this->relacionCiudadanoSeleccionado);

        return $ciudadano !== null && ! PresentacionPersona::visible($ciudadano, auth()->user())
            ? null
            : $ciudadano;
    }

    // -------------------------------------------------------------------------
    // Edición de Capa 1
    // -------------------------------------------------------------------------

    /**
     * Activa el modo edición simultáneo de todos los campos de Capa 1.
     * Solo si puedeEditar — supervision no puede modificar datos.
     *
     * @return void
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
     *
     * @return void
     */
    public function cancelarEdicion(): void
    {
        $c = $this->ciudadano;

        $this->nombre = $c->nombre ?? '';
        $this->apellido1 = $c->apellido1 ?? '';
        $this->apellido2 = $c->apellido2 ?? '';
        $this->fechaNacimiento = $c->fecha_nacimiento ?? '';
        $this->sexo = $c->sexo ?? '';
        $this->alias = $c->alias ?? '';
        $this->direccionTexto = $c->direccion_texto ?? '';
        $this->cargarDomicilio($c);
        $this->telefono = $c->telefono ?? '';
        $this->email = $c->email ?? '';
        $this->modoEdicion = false;
    }

    /**
     * Valida, normaliza y persiste los campos de Capa 1.
     * Autoriza con CiudadanoPolicy::update (403 si no puede editar).
     * DireccionObserver procesará geocodificación si cambia direccion_texto.
     *
     *
     * @return void
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function guardar(): void
    {
        Gate::authorize('update', $this->ciudadano);

        $this->validate([
            'nombre' => 'required|string|max:100',
            'apellido1' => 'required|string|max:100',
            'apellido2' => 'nullable|string|max:100',
            'fechaNacimiento' => 'nullable|date|before:today',
            'sexo' => ['required', Rule::in(array_keys($this->opcionesSexo))],
            'alias' => 'nullable|string|max:200',
            'domicilio.texto' => 'nullable|string|max:500',
            'domicilio.portal' => 'nullable|string|max:20',
            'domicilio.escalera' => 'nullable|string|max:20',
            'domicilio.piso' => 'nullable|string|max:20',
            'domicilio.puerta' => 'nullable|string|max:20',
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

        $this->ciudadano->update([
            'nombre' => $norm['nombre'] ?? $this->nombre,
            'apellido1' => $norm['apellido1'] ?? $this->apellido1,
            'apellido2' => $norm['apellido2'] ?? ($this->apellido2 ?: null),
            'fecha_nacimiento' => $this->fechaNacimiento ?: null,
            'sexo' => $this->sexo,
            'alias' => $this->alias ?: null,
            'telefono' => $norm['telefono'] ?? ($this->telefono ?: null),
            'email' => $norm['email'] ?? ($this->email ?: null),
            // El domicilio solo se reescribe si ha cambiado: guardar otros datos no debe
            // convertir en texto libre un domicilio elegido en el callejero
            ...($this->domicilio != $this->domicilioInicial ? app(SeleccionDomicilio::class)->atributos($this->domicilio) : []),
        ]);

        $this->direccionTexto = $this->ciudadano->direccion_texto ?? '';
        $this->cargarDomicilio($this->ciudadano);
        $this->modoEdicion = false;
        $this->dispatch('ciudadano-actualizado');
    }

    /**
     * Prepara el campo de domicilio con el guardado y recuerda cómo estaba.
     *
     * @param Ciudadano $ciudadano
     *
     * @return void
     */
    private function cargarDomicilio(Ciudadano $ciudadano): void
    {
        $this->domicilio = app(SeleccionDomicilio::class)->desdeModelo($ciudadano);
        $this->domicilioInicial = $this->domicilio;
    }

    // -------------------------------------------------------------------------
    // Gestión de relaciones
    // -------------------------------------------------------------------------

    /**
     * Abre el modal para crear una nueva relación.
     *
     * @return void
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
     *
     * @return void
     */
    public function abrirModalEditarRelacion(int $relacionId): void
    {
        if (! $this->puedeEditarRelaciones) {
            return;
        }

        $relacion = CiudadanoRelacion::where('ciudadano_id', $this->ciudadanoId)
            ->where('id', $relacionId)
            ->first();

        // Una relación con una persona protegida que no se puede ver no se edita (CLAUDE.md §3).
        if (! $relacion || ! $this->relacionVisible($relacion)) {
            return;
        }

        $this->relacionId = $relacionId;
        $this->relacionTipo = $relacion->tipo_relacion;
        $this->relacionCiudadanoSeleccionado = $relacion->ciudadano_relacionado_id;
        $this->relacionBusqueda = '';
        $this->relacionFechaInicio = $relacion->fecha_inicio->toDateString();
        $this->relacionFechaFin = $relacion->fecha_fin?->toDateString() ?? '';
        $this->relacionObservaciones = $relacion->observaciones ?? '';
        $this->modalRelacionAbierto = true;
    }

    /**
     * Cierra el modal y limpia el estado del formulario de relación.
     *
     * @return void
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
     *
     * @return void
     */
    public function toggleHistorialRelaciones(): void
    {
        $this->mostrarHistorialRelaciones = ! $this->mostrarHistorialRelaciones;
    }

    /**
     * Registra el ciudadano seleccionado en el buscador del modal de relación.
     *
     * @param int $ciudadanoId ID del ciudadano relacionado.
     *
     * @return void
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
     * Requiere CiudadanoPolicy::editarRelaciones; aborta con 403 si no.
     *
     *
     * @return void
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

            if (! $this->relacionVisible($relacion)) {
                abort(403);
            }

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

            // Con una persona protegida que no se puede ver no se crea relación: no se la
            // encuentra en el buscador, y una llamada directa tampoco lo consigue.
            if ($this->ciudadanoSeleccionadoRelacion === null) {
                abort(403);
            }

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
        unset($this->relacionesActivas, $this->relacionesHistoricas, $this->ucMiembros, $this->personasRelacionadas);
    }

    /**
     * Cierra una relación vigente estableciendo fecha_fin = hoy.
     * El modelo propaga el cierre al registro recíproco automáticamente.
     * Requiere CiudadanoPolicy::editarRelaciones; aborta con 403 si no.
     *
     * @param int $relacionId ID de la relación a cerrar.
     *
     * @return void
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

        if (! $this->relacionVisible($relacion)) {
            abort(403);
        }

        $relacion->update(['fecha_fin' => today()->toDateString()]);
        $this->cerrarModalRelacion();
        $this->relacionMensaje = 'Relación cerrada correctamente.';
        unset($this->relacionesActivas, $this->relacionesHistoricas, $this->ucMiembros, $this->personasRelacionadas);
    }

    // -------------------------------------------------------------------------
    // Documentos de identidad
    // -------------------------------------------------------------------------

    /**
     * Abre el modal de añadir documento. Solo si puedeEditar.
     *
     * @return void
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
     *
     * @return void
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
     * @return void
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
     * Solo cuando el ciudadano no tiene historia y la policy lo permite (solo un
     * profesional con rol intervención; HistoriaSocialPolicy::create).
     *
     * @return bool
     */
    #[Computed]
    public function puedeAbrirHistoria(): bool
    {
        return auth()->user()->can('create', HistoriaSocial::class) && ! $this->historiaSocial
            // R1: sin ningún centro, la ficha se consulta sin actuaciones
            && ! app(CentroDeUsuario::class)->sinCentro(auth()->user());
    }

    /**
     * Indica si el usuario puede registrar una nueva atención.
     *
     * @return bool
     */
    #[Computed]
    public function puedeCrearAtencion(): bool
    {
        return auth()->user()->can('atencion.crear')
            // R1: sin ningún centro, la ficha se consulta sin actuaciones
            && ! app(CentroDeUsuario::class)->sinCentro(auth()->user());
    }

    // -------------------------------------------------------------------------
    // Acciones — Atención e Historia Social
    // -------------------------------------------------------------------------

    /**
     * Crea la Historia Social del ciudadano, asigna al profesional autenticado
     * como responsable y redirige a la pantalla de intervención.
     * Solo ejecutable si el ciudadano no tiene historia social previa. Si el
     * usuario no está adscrito a un centro (RN-15), deja el motivo en el error
     * `abrirHistoria`.
     *
     * @return void
     */
    public function abrirHistoriaSocial(): void
    {
        $this->authorize('create', HistoriaSocial::class);

        if ($this->historiaSocial) {
            return;
        }

        /** @var User $profesional */
        $profesional = auth()->user();
        try {
            $historia = app(AperturaHistoriaService::class)->abrir($this->ciudadanoId, $profesional);
        } catch (\DomainException $e) {
            // RN-15: sin adscripción al centro no se abre; se explica en la ficha
            $this->addError('abrirHistoria', $e->getMessage());

            return;
        }

        // Quien abre la historia es intervención y la puede leer; el enlace lo decide la policy (R-02)
        $this->redirect(app(EnlacePersona::class)->url($profesional, $historia->ciudadano_id), navigate: true);
    }

    /**
     * Centro de la cita que se vincularía a la atención, si la hay: es el centro de la
     * operación (RN-16), el mismo que comprueba `RegistroAtencion` al crearse.
     *
     * @return Centro|null
     */
    private function centroDeLaAtencion(): ?Centro
    {
        return $this->vincularCita ? $this->citaVinculable?->centro : null;
    }

    /**
     * Abre el modal de nueva atención con los valores por defecto.
     *
     * @return void
     */
    public function abrirModalAtencion(): void
    {
        // RN-15: registrar una atención se rechaza al abrir el formulario, no solo al guardar (R1)
        app(CentroDeUsuario::class)->exigirActuacion(auth()->user(), $this->centroDeLaAtencion());
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
     *
     * @return void
     */
    public function cerrarModalAtencion(): void
    {
        $this->modalAtencionAbierto = false;
    }

    /**
     * Valida y persiste el nuevo registro de atención.
     * Invalida el computed del historial para que se recargue. Si el usuario no
     * está adscrito al centro (RN-15), deja el motivo en el error `atencion`.
     *
     * @return void
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

        try {
            $registro = RegistroAtencion::create([
                'ciudadano_id' => $this->ciudadanoId,
                // Completa la cita si sigue siendo vinculable (docs/modulo-citas.md §3.5.2)
                'cita_id' => $this->vincularCita ? $this->citaVinculable?->id : null,
                'tipo' => $this->atencionTipo,
                'fecha' => $this->atencionFecha,
                'profesional_id' => auth()->id(),
                'prestacion_id' => $this->atencionPrestacionId,
                'demanda' => $this->atencionDemanda,
                'respuesta' => $this->atencionRespuesta,
                'origen' => 'manual',
            ]);
        } catch (\DomainException $e) {
            // RN-15: sin adscripción al centro no se registra; el modal sigue abierto con el motivo
            $this->addError('atencion', $e->getMessage());

            return;
        }

        $this->atencionMensaje = 'Atención registrada correctamente.';
        $this->ultimaAtencionId = $registro->id;
        $this->modalAtencionAbierto = false;
        $this->citaPedida = null;
        $this->vincularCita = true;
        unset($this->historialAtenciones, $this->citaVinculable);
    }

    /**
     * Cita que se propone vincular al registro de atención: la de la agenda o
     * la de hoy de esta persona con el usuario (docs/modulo-citas.md §3.5).
     *
     * @return Cita|null
     */
    #[Computed]
    public function citaVinculable(): ?Cita
    {
        return app(AtencionCitaService::class)->citaVinculable($this->ciudadanoId, auth()->user(), $this->citaPedida);
    }

    /**
     * Si el usuario da citas: tras registrar una atención se le ofrece dar cita
     * (queda como cita generada del registro).
     *
     * @return bool
     */
    #[Computed]
    public function puedeDarCita(): bool
    {
        return auth()->user()->can('citas.gestionar') || auth()->user()->can('citas.supervisar');
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    /**
     * Renderiza la ficha del ciudadano.
     *
     * @return View
     */
    public function render(): View
    {
        return view('ciudadania::livewire.ficha-ciudadano-page');
    }

    /**
     * Si el usuario puede ver a la persona relacionada de una relación del titular.
     *
     * Las acciones sobre la relación (editar, cerrar) solo se permiten si la puede
     * ver: con un colectivo protegido ajeno solo se sabe que la relación existe.
     *
     * @param CiudadanoRelacion $relacion Relación del titular.
     *
     * @return bool
     */
    private function relacionVisible(CiudadanoRelacion $relacion): bool
    {
        $persona = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->find($relacion->ciudadano_relacionado_id);

        return $persona !== null && PresentacionPersona::visible($persona, auth()->user());
    }
}
