<?php

namespace Modules\Prestaciones\Http\Livewire;

use App\Enums\AccionAuditEnum;
use App\Models\Ciudadano;
use App\Models\User;
use App\Services\AmbitoUsuario;
use App\Services\AuditService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\Ciudadania\Models\UnidadConvivenciaMiembro;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\DocumentoVinculo;
use Modules\Documentos\Models\TipoDocumental;
use Modules\Prestaciones\Data\ActualizacionExpediente;
use Modules\Prestaciones\Data\IncidenciaExpediente;
use Modules\Prestaciones\Data\LineaResolucionDto;
use Modules\Prestaciones\Data\ResolucionDto;
use Modules\Prestaciones\Enums\EstadoSolicitudPrestacion;
use Modules\Prestaciones\Enums\SentidoResolucion;
use Modules\Prestaciones\Enums\ViaAcceso;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Prestaciones\Models\SolicitudPrestacion;
use Modules\Prestaciones\Services\Gestor\AdaptadorGestorExpedientes;
use Modules\Prestaciones\Services\SolicitudPrestacionService;
use Throwable;

/**
 * Pantalla de una solicitud de prestación (fase 3 bis, paso 5.2).
 *
 * La usan el trabajador social (preparación y seguimiento) y tramitación (toma, envío,
 * registro manual, incidencias). Tramitación ve identificación y contacto de la persona,
 * parámetros, documentos de la solicitud e historial, **nunca** la Historia Social, apuntes ni
 * motivo profesional (RN-10): esta pantalla no muestra nada de eso ni enlaza a la historia para
 * quien solo tramita. Todo acceso a la solicitud de una persona protegida se audita como
 * `acceso_restringido`. Las reglas las aplica SolicitudPrestacionService.
 *
 * @property-read SolicitudPrestacion $solicitud
 * @property-read Ciudadano $persona
 * @property-read array<string, bool> $puede
 */
class SolicitudPrestacionPage extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $solicitudId;

    /** @var array<string, mixed> Datos del formulario */
    public array $datos = [];

    public ?int $documentoId = null;

    public ?int $tipoDocumentalId = null;

    /** @var TemporaryUploadedFile|null */
    public $fichero = null;

    public ?int $personaId = null;

    public ?int $sugeridaId = null;

    /** Acción en curso: soltar, devolver, enviar, anular, estado, incidencia, resolucion, atender. */
    public ?string $panel = null;

    public string $nota = '';

    public string $justificacion = '';

    public string $numeroExpediente = '';

    public ?string $fechaPresentacion = null;

    /** @var array<string, mixed> Registro manual */
    public array $manual = [];

    /** @var list<array<string, mixed>> Líneas de una resolución manual */
    public array $lineas = [];

    public ?int $incidenciaId = null;

    /** @var list<int> Documentos que se aportan a una incidencia */
    public array $documentosIncidencia = [];

    public string $mensaje = '';

    /**
     * Autoriza y audita el acceso; carga los datos del formulario.
     *
     * @param SolicitudPrestacion $solicitud
     *
     * @return void
     */
    public function mount(SolicitudPrestacion $solicitud): void
    {
        $this->authorize('view', $solicitud);
        $this->solicitudId = $solicitud->id;
        $this->datos = $solicitud->ficha?->datos ?? [];
        $this->sugeridaId = $solicitud->sugerida_id;
        $this->auditarAcceso($solicitud);
    }

    /**
     * Solicitud con sus relaciones.
     *
     * @return SolicitudPrestacion
     */
    #[Computed]
    public function solicitud(): SolicitudPrestacion
    {
        return SolicitudPrestacion::with(['prestacion', 'ficha', 'eventos.usuario.profesional', 'resolucion.lineas.prestacion', 'centroSolicitud', 'unidadTramitadora', 'responsable.profesional', 'sugerida.profesional', 'creadaPor.profesional', 'personasAfectadas'])
            ->findOrFail($this->solicitudId);
    }

    /**
     * Persona titular (identificación y contacto, RN-10).
     *
     * @return Ciudadano
     */
    #[Computed]
    public function persona(): Ciudadano
    {
        return Ciudadano::withoutGlobalScopes()->with('documentoVigente')->findOrFail($this->solicitud->ciudadano_id);
    }

    /**
     * Qué puede hacer el usuario ahora (para mostrar las acciones; el servicio lo vuelve a comprobar).
     *
     * @return array<string, bool>
     */
    #[Computed]
    public function puede(): array
    {
        $s = $this->solicitud;
        $u = Auth::user();
        $gate = Gate::forUser($u);
        $tramita = $gate->allows('tramitar', $s);
        $responsable = $s->estado === EstadoSolicitudPrestacion::EnPreparacion && $tramita && $s->responsable_id === $u->id;
        $editaBorrador = $gate->allows('editarBorrador', $s);

        return [
            'editar' => $editaBorrador || $responsable,
            'documentos' => $editaBorrador || $responsable || ($s->estado === EstadoSolicitudPrestacion::Enviada && $tramita),
            'aBandeja' => $editaBorrador,
            'tomar' => $s->estado === EstadoSolicitudPrestacion::EnBandeja && $tramita,
            'responsable' => $responsable,
            'anular' => $s->estado->antesDelEnvio() && ($s->creada_por_id === $u->id || ($tramita && $s->unidad_tramitadora_id !== null) || $u->hasRole('supervision')),
            'manual' => $s->estado === EstadoSolicitudPrestacion::Enviada && $gate->allows('registrarManual', $s),
            'consultar' => $s->estado === EstadoSolicitudPrestacion::Enviada && $s->numero_expediente !== null
                && app(SolicitudPrestacionService::class)->gestor()->admite(AdaptadorGestorExpedientes::CONSULTAR),
            'atender' => $s->estado === EstadoSolicitudPrestacion::Enviada && $tramita,
            'verHistoria' => $gate->allows('view', $s->historia),
            'envioManual' => $s->via === ViaAcceso::ExpedienteExterno
                && ! app(SolicitudPrestacionService::class)->gestor()->admite(AdaptadorGestorExpedientes::INICIAR),
        ];
    }

    /**
     * Documentos exigidos (de la configuración de la solicitud) con su estado.
     *
     * @return Collection<int, array{nombre: string, obligatorio: bool, aportado: bool}>
     */
    #[Computed]
    public function documentosExigidos(): Collection
    {
        $aportados = $this->documentosVinculados->pluck('tipo_documental_id')->all();

        return collect($this->solicitud->configuracion_snapshot['documentos'] ?? [])->map(fn (array $d) => [
            'nombre' => $d['nombre'],
            'obligatorio' => (bool) $d['obligatorio'],
            'aportado' => in_array($d['tipo_documental_id'], $aportados, true),
        ]);
    }

    /**
     * Documentos vinculados a la solicitud.
     *
     * @return Collection<int, Documento>
     */
    #[Computed]
    public function documentosVinculados(): Collection
    {
        return Documento::with('tipo')->disponibles()->whereIn('id', $this->solicitud->vinculosDocumentos()->pluck('documento_id'))->get();
    }

    /**
     * Documentos de la carpeta de la persona que aún no están en la solicitud.
     *
     * @return Collection<int, Documento>
     */
    #[Computed]
    public function documentosCarpeta(): Collection
    {
        $ids = DocumentoVinculo::query()->activos()
            ->where('vinculable_type', (new Ciudadano)->getMorphClass())->where('vinculable_id', $this->solicitud->ciudadano_id)
            ->pluck('documento_id');

        return Documento::with('tipo')->disponibles()->whereIn('id', $ids)->whereNotIn('id', $this->documentosVinculados->pluck('id'))->orderByDesc('id')->get();
    }

    /**
     * Tipos documentales que se pueden subir (los que admite la carpeta de la persona).
     *
     * @return Collection<int, TipoDocumental>
     */
    #[Computed]
    public function tiposDocumentales(): Collection
    {
        return TipoDocumental::where('activo', true)->orderBy('nombre')->get()
            ->filter(fn (TipoDocumental $t) => $t->permiteVincularA(Ciudadano::class))->values();
    }

    /**
     * Miembros activos de la unidad de convivencia que se pueden añadir como afectados.
     *
     * @return Collection<int, Ciudadano>
     */
    #[Computed]
    public function miembrosUc(): Collection
    {
        $unidades = UnidadConvivenciaMiembro::where('ciudadano_id', $this->solicitud->ciudadano_id)->whereNull('fecha_fin')->pluck('unidad_convivencia_id');
        $ids = UnidadConvivenciaMiembro::whereIn('unidad_convivencia_id', $unidades)->whereNull('fecha_fin')
            ->where('ciudadano_id', '!=', $this->solicitud->ciudadano_id)->pluck('ciudadano_id');

        return Ciudadano::withoutGlobalScopes()->whereIn('id', $ids)->whereNotIn('id', $this->solicitud->personasAfectadas->pluck('id'))->get();
    }

    /**
     * Personas de tramitación que se pueden sugerir.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function tramitadores(): Collection
    {
        return User::permission('solicitud.tramitar')->with('profesional')->get()
            ->reject(fn (User $u) => $u->hasRole('supervision'))
            ->sortBy(fn (User $u) => $u->nombreVisible())->values();
    }

    /**
     * Prestaciones del catálogo para las líneas de una resolución manual.
     *
     * @return Collection<int, Prestacion>
     */
    #[Computed]
    public function prestacionesCatalogo(): Collection
    {
        return Prestacion::query()->activas()->orderBy('nombre')->get(['id', 'nombre']);
    }

    /**
     * Guarda los datos del formulario.
     *
     * @return void
     */
    public function guardarDatos(): void
    {
        $this->ejecutar(fn (SolicitudPrestacionService $s) => $s->guardarDatos($this->solicitud, $this->datos, Auth::user()), 'Datos guardados.');
    }

    /**
     * Vincula el documento elegido de la carpeta.
     *
     * @return void
     */
    public function vincularDocumento(): void
    {
        $documento = $this->documentosCarpeta->firstWhere('id', $this->documentoId);
        if ($documento === null) {
            $this->addError('documentoId', 'Elige un documento de la carpeta.');

            return;
        }

        $this->ejecutar(fn (SolicitudPrestacionService $s) => $s->vincularDocumento($this->solicitud, $documento, Auth::user()), 'Documento vinculado.');
        $this->documentoId = null;
    }

    /**
     * Sube un documento para la solicitud (queda también en la carpeta de la persona).
     *
     * @return void
     */
    public function subirDocumento(): void
    {
        $this->validate(['fichero' => ['required', 'file', 'max:20480'], 'tipoDocumentalId' => ['required', 'integer']], [], ['fichero' => 'fichero', 'tipoDocumentalId' => 'tipo documental']);
        $tipo = $this->tiposDocumentales->firstWhere('id', $this->tipoDocumentalId);

        if ($tipo === null) {
            $this->addError('tipoDocumentalId', 'Elige un tipo documental válido.');

            return;
        }

        $this->ejecutar(fn (SolicitudPrestacionService $s) => $s->subirDocumento($this->solicitud, $this->fichero, $tipo, Auth::user()), 'Documento subido y vinculado.');
        $this->reset(['fichero', 'tipoDocumentalId']);
    }

    /**
     * Quita un documento de la solicitud.
     *
     * @param int $documentoId
     *
     * @return void
     */
    public function desvincularDocumento(int $documentoId): void
    {
        $documento = $this->documentosVinculados->firstWhere('id', $documentoId);
        if ($documento !== null) {
            $this->ejecutar(fn (SolicitudPrestacionService $s) => $s->desvincularDocumento($this->solicitud, $documento, Auth::user()), 'Documento retirado de la solicitud.');
        }
    }

    /**
     * Añade una persona afectada.
     *
     * @return void
     */
    public function anadirPersona(): void
    {
        $persona = Ciudadano::withoutGlobalScopes()->find($this->personaId);
        if ($persona === null) {
            $this->addError('personaId', 'Elige una persona.');

            return;
        }

        $this->ejecutar(fn (SolicitudPrestacionService $s) => $s->anadirPersona($this->solicitud, $persona, Auth::user()), 'Persona añadida.');
        $this->personaId = null;
    }

    /**
     * Guarda la sugerencia de tramitador.
     *
     * @return void
     */
    public function sugerir(): void
    {
        $this->ejecutar(fn (SolicitudPrestacionService $s) => $s->sugerir($this->solicitud, $this->sugeridaId ? User::find($this->sugeridaId) : null, Auth::user()), 'Sugerencia guardada.');
    }

    /**
     * Pasa la solicitud a la bandeja (guardando antes los datos).
     *
     * @return void
     */
    public function pasarABandeja(): void
    {
        $this->ejecutar(function (SolicitudPrestacionService $s): void {
            if ($this->solicitud->ficha !== null) {
                $s->guardarDatos($this->solicitud, $this->datos, Auth::user());
            }
            $s->pasarABandeja($this->solicitud, Auth::user());
        }, 'Solicitud en la bandeja de tramitación.');
    }

    /**
     * Toma la solicitud.
     *
     * @return void
     */
    public function tomar(): void
    {
        $this->ejecutar(fn (SolicitudPrestacionService $s) => $s->tomar($this->solicitud, Auth::user()), 'Solicitud tomada.');
    }

    /**
     * Abre el panel de una acción.
     *
     * @param string $panel
     *
     * @return void
     */
    public function abrirPanel(string $panel): void
    {
        $this->resetErrorBag();
        $this->reset(['nota', 'justificacion', 'numeroExpediente', 'fechaPresentacion', 'manual', 'incidenciaId', 'documentosIncidencia']);
        $this->panel = $panel;
        $this->lineas = $panel === 'resolucion' ? [['prestacion_id' => $this->solicitud->prestacion_id, 'concedida' => true, 'motivo' => '', 'importe' => '', 'intensidad' => '']] : [];
        $this->manual = match ($panel) {
            'estado' => ['codigo' => '', 'texto' => '', 'normalizado' => 'en_tramite', 'fecha' => today()->toDateString()],
            'incidencia' => ['tipo' => 'requerimiento_documentacion', 'fecha' => today()->toDateString(), 'descripcion' => '', 'plazo' => null],
            'resolucion' => ['fecha' => today()->toDateString(), 'sentido' => 'favorable'],
            default => [],
        };
    }

    /**
     * Confirma la acción del panel abierto.
     *
     * @return void
     */
    public function confirmar(): void
    {
        $u = Auth::user();
        $s = $this->solicitud;

        $hecho = match ($this->panel) {
            'soltar' => $this->ejecutar(fn ($srv) => $srv->soltar($s, $u, $this->nota ?: null), 'Solicitud devuelta a la bandeja.'),
            'devolver' => $this->ejecutar(fn ($srv) => $srv->devolver($s, $u, $this->nota), 'Solicitud devuelta al profesional.'),
            'enviar' => $this->ejecutar(fn ($srv) => $srv->enviar($s, $u, $this->justificacion ?: null, $this->numeroExpediente ?: null,
                $this->fechaPresentacion ? Carbon::parse($this->fechaPresentacion) : null), 'Solicitud enviada.'),
            'anular' => $this->ejecutar(fn ($srv) => $srv->anular($s, $u, $this->nota), 'Solicitud anulada.'),
            'estado', 'incidencia', 'resolucion' => $this->ejecutar(fn ($srv) => $srv->registrarManual($s, $this->actualizacionManual(), $u), 'Registrado.'),
            'atender' => $this->ejecutar(fn ($srv) => $srv->atenderIncidencia($s, (int) $this->incidenciaId, $u, array_map('intval', $this->documentosIncidencia)), 'Incidencia atendida.'),
            default => false,
        };

        if ($hecho) {
            $this->panel = null;
        }
    }

    /**
     * Añade una línea a la resolución manual.
     *
     * @return void
     */
    public function anadirLinea(): void
    {
        $this->lineas[] = ['prestacion_id' => $this->solicitud->prestacion_id, 'concedida' => true, 'motivo' => '', 'importe' => '', 'intensidad' => ''];
    }

    /**
     * «Consultar ahora» el estado del expediente.
     *
     * @return void
     */
    public function consultarAhora(): void
    {
        $this->ejecutar(fn (SolicitudPrestacionService $s) => $s->consultarAhora($this->solicitud, Auth::user()), 'Estado consultado.');
    }

    /**
     * Pinta la pantalla con el layout del rol (Intervención o Tramitación).
     *
     * @return View
     */
    public function render(): View
    {
        return view('prestaciones::livewire.solicitud-page')
            ->layout($this->puede['verHistoria'] ? 'layouts.operativo' : 'layouts.tramitacion');
    }

    /**
     * Construye la actualización del registro manual según el panel.
     *
     * @return ActualizacionExpediente
     *
     * @throws DomainException
     */
    private function actualizacionManual(): ActualizacionExpediente
    {
        $m = $this->manual;
        $fecha = Carbon::parse($m['fecha'] ?? today());

        return match ($this->panel) {
            'estado' => new ActualizacionExpediente(trim($m['codigo'] ?? '') ?: ($m['normalizado'] ?? null), trim($m['texto'] ?? '') ?: null, $fecha, estadoNormalizado: $m['normalizado'] ?? null),
            'incidencia' => new ActualizacionExpediente(null, null, $fecha, [new IncidenciaExpediente(
                $m['tipo'] ?? 'otra', $fecha, trim($m['descripcion'] ?? '') ?: throw new DomainException('Describe la incidencia.'),
                ! empty($m['plazo']) ? Carbon::parse($m['plazo']) : null,
            )]),
            default => new ActualizacionExpediente(null, null, $fecha, resolucion: new ResolucionDto(
                $fecha,
                SentidoResolucion::from($m['sentido'] ?? 'favorable'),
                array_map(fn (array $l) => new LineaResolucionDto(
                    (int) $l['prestacion_id'], (bool) $l['concedida'], $l['motivo'] ?: null, $l['importe'] ?: null, $l['intensidad'] ?: null,
                ), $this->lineas),
            )),
        };
    }

    /**
     * Ejecuta una operación del servicio y traduce sus rechazos a errores de la pantalla.
     *
     * @param callable(SolicitudPrestacionService): mixed $operacion
     * @param string $mensaje
     *
     * @return bool
     */
    private function ejecutar(callable $operacion, string $mensaje): bool
    {
        try {
            $operacion(app(SolicitudPrestacionService::class));
        } catch (AuthorizationException|DomainException|InvalidArgumentException $e) {
            $this->addError('accion', $e->getMessage());

            return false;
        } catch (ValidationException $e) {
            // Errores por campo (p. ej. una clave de catálogo no vigente): Livewire los pinta en su campo
            throw $e;
        } catch (Throwable $e) {
            report($e);
            $this->addError('accion', 'No se ha podido completar la operación.');

            return false;
        }

        $this->mensaje = $mensaje;
        unset($this->solicitud, $this->puede, $this->documentosVinculados, $this->documentosCarpeta, $this->documentosExigidos, $this->miembrosUc);

        return true;
    }

    /**
     * Audita la apertura de la solicitud de una persona protegida como acceso restringido (RN-10).
     *
     * @param SolicitudPrestacion $solicitud
     *
     * @return void
     */
    private function auditarAcceso(SolicitudPrestacion $solicitud): void
    {
        $historia = $solicitud->historia;

        if ($historia !== null && app(AmbitoUsuario::class)->historiaProtegida($historia)) {
            app(AuditService::class)->registrarAcceso(Auth::user(), $solicitud, AccionAuditEnum::AccesoRestringido, $solicitud->ciudadano_id, ['pantalla' => 'solicitud_prestacion']);
        }
    }
}
