<?php

namespace Modules\Prestaciones\Services;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;
use Modules\Ciudadania\Models\UnidadConvivenciaMiembro;
use Modules\Documentos\Data\DatosIngesta;
use Modules\Documentos\Enums\CanalCaptura;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\DocumentoVinculo;
use Modules\Documentos\Models\TipoDocumental;
use Modules\Documentos\Services\CicloVidaDocumentoService;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\PlanObjetivo;
use Modules\Intervencion\Models\SeguimientoPlan;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Services\AlertaService;
use Modules\Prestaciones\Data\ActualizacionExpediente;
use Modules\Prestaciones\Data\IncidenciaExpediente;
use Modules\Prestaciones\Data\SolicitudParaGestor;
use Modules\Prestaciones\Enums\AccionEventoSolicitud;
use Modules\Prestaciones\Enums\EstadoSolicitudPrestacion;
use Modules\Prestaciones\Enums\OrigenActualizacion;
use Modules\Prestaciones\Enums\ViaAcceso;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Prestaciones\Models\SolicitudPrestacion;
use Modules\Prestaciones\Models\SolicitudPrestacionEvento;
use Modules\Prestaciones\Services\Gestor\AdaptadorGestorExpedientes;
use Throwable;

/**
 * Único punto de escritura de las solicitudes de prestación (docs/modulo-solicitudes-expedientes.md §3).
 *
 * El trabajador social crea la solicitud en borrador (con copia de la configuración de la
 * prestación), la completa y la pasa a la bandeja de la unidad tramitadora; tramitación la
 * toma, la suelta, la devuelve o la envía; las actualizaciones del expediente llegan por las
 * cuatro vías de RN-07 y pasan todas por registrarActualizacion(). Cada acción deja un evento
 * inmutable (RN-11). VIDA no se comunica con el ciudadano (RN-06): solo avisa a los profesionales.
 */
class SolicitudPrestacionService
{
    /**
     * Inyecta las dependencias.
     *
     * @param AdaptadorGestorExpedientes $gestor
     * @param AlertaService $alertas
     * @param ResolucionService $resoluciones
     */
    public function __construct(
        private readonly AdaptadorGestorExpedientes $gestor,
        private readonly AlertaService $alertas,
        private readonly ResolucionService $resoluciones,
    ) {}

    /**
     * Adaptador del gestor activo (para que la interfaz sepa qué operaciones ofrecer).
     *
     * @return AdaptadorGestorExpedientes
     */
    public function gestor(): AdaptadorGestorExpedientes
    {
        return $this->gestor;
    }

    /**
     * Crea una solicitud en borrador de una prestación con vía de tramitación.
     *
     * @param Ciudadano $ciudadano Titular.
     * @param Prestacion $prestacion Prestación de tramitación que ofrece el centro.
     * @param User $ts Quien la crea (con `solicitud.crear`).
     * @param PlanDeIntervencion|null $plan
     * @param PlanObjetivo|null $objetivo Debe ser del plan.
     * @param Centro|null $contexto Centro desde el que se solicita; null = centro activo del usuario.
     * @param SeguimientoPlan|null $seguimiento Seguimiento desde el que se solicita (paso 7).
     *
     * @return SolicitudPrestacion
     *
     * @throws AuthorizationException|DomainException
     */
    public function crear(Ciudadano $ciudadano, Prestacion $prestacion, User $ts, ?PlanDeIntervencion $plan = null, ?PlanObjetivo $objetivo = null, ?Centro $contexto = null, ?SeguimientoPlan $seguimiento = null): SolicitudPrestacion
    {
        if (! Gate::forUser($ts)->allows('create', SolicitudPrestacion::class)) {
            throw new AuthorizationException('No puedes crear solicitudes de prestación.');
        }

        if (! $prestacion->via_acceso?->esTramitacion()) {
            throw new DomainException('Esa prestación no se tramita con solicitud.');
        }

        $centro = $contexto ?? app(CentroDeUsuario::class)->centroActivo($ts);
        // R1: solicitar una prestación es una actuación en el centro del contexto o el activo
        app(CentroDeUsuario::class)->exigirAtencion($ts, $centro);

        if (! app(ConfiguracionEfectivaCentro::class)->prestaciones($centro)->contains('id', $prestacion->id)) {
            throw new DomainException("{$centro->nombre} no ofrece esa prestación.");
        }

        if ($objetivo !== null && $objetivo->plan_id !== $plan?->id) {
            throw new DomainException('El objetivo no es de ese plan.');
        }

        $historia = HistoriaSocial::withoutGlobalScopes()->where('ciudadano_id', $ciudadano->id)
            ->whereIn('estado', ['abierta', 'en_seguimiento'])->latest()->first()
            ?? throw new DomainException('La persona no tiene una Historia Social abierta.');

        return DB::transaction(function () use ($ciudadano, $prestacion, $ts, $plan, $objetivo, $centro, $seguimiento, $historia): SolicitudPrestacion {
            $prestacion->loadMissing(['documentosExigidos.tipoDocumental', 'formularioSolicitud']);
            $formulario = $prestacion->formularioSolicitud;

            $ficha = $formulario === null ? null : Ficha::create([
                'historia_id' => $historia->id,
                'tipo_ficha_id' => $formulario->id,
                'schema_snapshot' => $formulario->schema,
                'profesional_id' => $ts->id,
                'datos' => [],
                'completada' => false,
            ]);

            $solicitud = SolicitudPrestacion::create([
                'ciudadano_id' => $ciudadano->id,
                'historia_id' => $historia->id,
                'prestacion_id' => $prestacion->id,
                'configuracion_snapshot' => $this->snapshot($prestacion),
                'via' => $prestacion->via_acceso,
                'plan_id' => $plan?->id,
                'plan_objetivo_id' => $objetivo?->id,
                'seguimiento_id' => $seguimiento?->id,
                'centro_solicitud_id' => $centro->id,
                'creada_por_id' => $ts->id,
                'estado' => EstadoSolicitudPrestacion::Borrador,
                'ficha_id' => $ficha?->id,
            ]);

            $this->evento($solicitud, AccionEventoSolicitud::Creada, null, $ts);

            return $solicitud;
        });
    }

    /**
     * Guarda los datos del formulario (en borrador, quien la creó; en preparación, la responsable).
     *
     * @param SolicitudPrestacion $solicitud
     * @param array<string, mixed> $datos
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function guardarDatos(SolicitudPrestacion $solicitud, array $datos, User $actor): void
    {
        $this->exigirEdicion($solicitud, $actor);

        if ($solicitud->ficha === null) {
            throw new DomainException('Esta prestación no tiene formulario de solicitud.');
        }

        $solicitud->ficha->update(['datos' => $datos]);
    }

    /**
     * Vincula a la solicitud un documento de la carpeta de la persona.
     *
     * @param SolicitudPrestacion $solicitud
     * @param Documento $documento Vinculado (activo) a la persona titular.
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function vincularDocumento(SolicitudPrestacion $solicitud, Documento $documento, User $actor): void
    {
        $this->exigirDocumentos($solicitud, $actor);

        // Un documento retirado (fase 4) o destruido ya no se vincula
        if (! $documento->estaDisponible()) {
            throw new DomainException('El documento ya no está disponible.');
        }

        $dePersona = DocumentoVinculo::query()->activos()->where('documento_id', $documento->id)
            ->where('vinculable_type', (new Ciudadano)->getMorphClass())->where('vinculable_id', $solicitud->ciudadano_id)->exists();
        if (! $dePersona) {
            throw new DomainException('Solo se vinculan documentos de la carpeta de la persona.');
        }

        if ($solicitud->vinculosDocumentos()->where('documento_id', $documento->id)->exists()) {
            return;
        }

        DB::transaction(function () use ($solicitud, $documento, $actor): void {
            DocumentoVinculo::create([
                'documento_id' => $documento->id,
                'vinculable_type' => $solicitud->getMorphClass(),
                'vinculable_id' => $solicitud->id,
                'activo' => true,
                'fecha_alta' => now(),
                'creado_por' => $actor->id,
            ]);
            $this->evento($solicitud, AccionEventoSolicitud::DocumentoVinculado, null, $actor, ['documento_id' => $documento->id]);
        });
    }

    /**
     * Sube un documento para la solicitud: queda en la carpeta de la persona y vinculado a la solicitud.
     *
     * @param SolicitudPrestacion $solicitud
     * @param UploadedFile $fichero
     * @param TipoDocumental $tipo
     * @param User $actor
     *
     * @return Documento
     *
     * @throws AuthorizationException|DomainException
     */
    public function subirDocumento(SolicitudPrestacion $solicitud, UploadedFile $fichero, TipoDocumental $tipo, User $actor): Documento
    {
        $this->exigirDocumentos($solicitud, $actor);

        // Por el alta de la custodia: queda en la carpeta de la persona y vinculado a la solicitud
        $documento = app(CicloVidaDocumentoService::class)->altaDocumento($fichero, new DatosIngesta(
            tipo: $tipo,
            usuario: $actor,
            canal: CanalCaptura::Presencial,
            vinculos: [$solicitud->ciudadano, $solicitud],
            nombreOriginal: $fichero->getClientOriginalName(),
        ));

        $this->evento($solicitud, AccionEventoSolicitud::DocumentoVinculado, null, $actor, ['documento_id' => $documento->id, 'subido' => true]);

        return $documento;
    }

    /**
     * Retira un documento de la solicitud (sigue en la carpeta de la persona).
     *
     * @param SolicitudPrestacion $solicitud
     * @param Documento $documento
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function desvincularDocumento(SolicitudPrestacion $solicitud, Documento $documento, User $actor): void
    {
        $this->exigirDocumentos($solicitud, $actor);

        $vinculo = $solicitud->vinculosDocumentos()->where('documento_id', $documento->id)->first();
        if ($vinculo === null) {
            return;
        }

        DB::transaction(function () use ($solicitud, $documento, $vinculo, $actor): void {
            $vinculo->update(['activo' => false, 'fecha_baja' => now(), 'baja_por' => $actor->id]);
            $this->evento($solicitud, AccionEventoSolicitud::DocumentoDesvinculado, null, $actor, ['documento_id' => $documento->id]);
        });
    }

    /**
     * Sugiere una persona de tramitación (no es asignación: sigue en la bandeja, RN-03).
     *
     * @param SolicitudPrestacion $solicitud
     * @param User|null $sugerida
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function sugerir(SolicitudPrestacion $solicitud, ?User $sugerida, User $actor): void
    {
        $this->exigirEdicion($solicitud, $actor);

        if ($sugerida !== null && ! $sugerida->can('solicitud.tramitar')) {
            throw new DomainException('Solo se sugiere a alguien de tramitación.');
        }

        $solicitud->update(['sugerida_id' => $sugerida?->id]);
    }

    /**
     * Añade una persona afectada: solo miembros activos de la unidad de convivencia del titular.
     *
     * @param SolicitudPrestacion $solicitud
     * @param Ciudadano $persona
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function anadirPersona(SolicitudPrestacion $solicitud, Ciudadano $persona, User $actor): void
    {
        $this->exigirEdicion($solicitud, $actor);

        $unidades = UnidadConvivenciaMiembro::query()->where('ciudadano_id', $solicitud->ciudadano_id)->whereNull('fecha_fin')->pluck('unidad_convivencia_id');
        $esMiembro = $persona->id !== $solicitud->ciudadano_id && UnidadConvivenciaMiembro::query()
            ->whereIn('unidad_convivencia_id', $unidades)->where('ciudadano_id', $persona->id)->whereNull('fecha_fin')->exists();

        if (! $esMiembro) {
            throw new DomainException('Solo se añaden miembros activos de la unidad de convivencia de la persona.');
        }

        $solicitud->personasAfectadas()->syncWithoutDetaching([$persona->id]);
    }

    /**
     * Pasa la solicitud a la bandeja de su unidad tramitadora (RN-02).
     *
     * @param SolicitudPrestacion $solicitud En borrador.
     * @param User $actor Quien la creó (o puede editar el borrador).
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function pasarABandeja(SolicitudPrestacion $solicitud, User $actor): void
    {
        $this->exigirEstado($solicitud, [EstadoSolicitudPrestacion::Borrador]);
        $this->exigirEdicion($solicitud, $actor);

        $config = $solicitud->configuracion_snapshot;
        $unidad = ($config['unidad_tramitadora_tipo'] ?? null) === 'uo'
            ? $config['unidad_tramitadora_id']
            : $solicitud->centroSolicitud->unidad_organizativa_id;

        $this->cambiar($solicitud, EstadoSolicitudPrestacion::EnBandeja, AccionEventoSolicitud::ABandeja, $actor, ['unidad_tramitadora_id' => $unidad]);
    }

    /**
     * Toma una solicitud de la bandeja: queda como responsable (RN-03).
     *
     * @param SolicitudPrestacion $solicitud En bandeja.
     * @param User $actor De tramitación de la unidad.
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function tomar(SolicitudPrestacion $solicitud, User $actor): void
    {
        $this->exigirTramitacion($solicitud, $actor);
        $this->exigirEstado($solicitud, [EstadoSolicitudPrestacion::EnBandeja], 'La solicitud ya la tiene alguien o no está en la bandeja.');

        $this->cambiar($solicitud, EstadoSolicitudPrestacion::EnPreparacion, AccionEventoSolicitud::Tomada, $actor, ['responsable_id' => $actor->id]);
    }

    /**
     * Suelta una solicitud tomada: vuelve a la bandeja sin responsable.
     *
     * @param SolicitudPrestacion $solicitud
     * @param User $actor La responsable.
     * @param string|null $nota
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function soltar(SolicitudPrestacion $solicitud, User $actor, ?string $nota = null): void
    {
        $this->exigirResponsable($solicitud, $actor);

        $this->cambiar($solicitud, EstadoSolicitudPrestacion::EnBandeja, AccionEventoSolicitud::Soltada, $actor, ['responsable_id' => null], [], $nota);
    }

    /**
     * Devuelve la solicitud al trabajador social con una nota (a borrador) y le avisa.
     *
     * @param SolicitudPrestacion $solicitud
     * @param User $actor La responsable.
     * @param string $nota Obligatoria.
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function devolver(SolicitudPrestacion $solicitud, User $actor, string $nota): void
    {
        $this->exigirResponsable($solicitud, $actor);
        $nota = $this->exigirTexto($nota, 'Explica por qué la devuelves.');

        DB::transaction(function () use ($solicitud, $actor, $nota): void {
            $this->cambiar($solicitud, EstadoSolicitudPrestacion::Borrador, AccionEventoSolicitud::Devuelta, $actor, ['responsable_id' => null, 'unidad_tramitadora_id' => null], [], $nota);
            $this->avisar($solicitud->creada_por_id, $solicitud, 'Solicitud devuelta', "Tramitación te ha devuelto la solicitud de {$solicitud->prestacion->nombre}: {$nota}");
        });
    }

    /**
     * Envía la solicitud (RN-05): al gestor, con registro manual o a la unidad interna.
     *
     * @param SolicitudPrestacion $solicitud En preparación.
     * @param User $actor La responsable.
     * @param string|null $justificacion Obligatoria si faltan documentos obligatorios.
     * @param string|null $numeroExpediente Obligatorio con el adaptador manual (expediente externo).
     * @param Carbon|null $fechaPresentacion Obligatoria con el adaptador manual.
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function enviar(SolicitudPrestacion $solicitud, User $actor, ?string $justificacion = null, ?string $numeroExpediente = null, ?Carbon $fechaPresentacion = null): void
    {
        $this->exigirResponsable($solicitud, $actor);

        $faltan = $this->documentosObligatoriosPendientes($solicitud);
        if ($faltan !== [] && trim((string) $justificacion) === '') {
            throw new DomainException('Faltan documentos obligatorios ('.implode(', ', $faltan).'): justifica el envío.');
        }

        $datos = ['fecha_envio' => today(), 'ultima_actualizacion_en' => now()];

        if ($solicitud->via === ViaAcceso::ExpedienteExterno) {
            if ($this->gestor->admite(AdaptadorGestorExpedientes::INICIAR)) {
                try {
                    $datos['numero_expediente'] = $this->gestor->iniciarSolicitud($this->paraGestor($solicitud));
                } catch (Throwable $e) {
                    Log::warning("Solicitud {$solicitud->id}: el gestor de expedientes ({$this->gestor->nombre()}) no la ha aceptado: {$e->getMessage()}");

                    throw new DomainException('El gestor de expedientes no ha respondido. La solicitud sigue en preparación; inténtalo más tarde.');
                }
            } else {
                if (trim((string) $numeroExpediente) === '' || $fechaPresentacion === null) {
                    throw new DomainException('Indica el número de expediente y la fecha de presentación.');
                }
                $datos['numero_expediente'] = trim($numeroExpediente);
                $datos['fecha_envio'] = $fechaPresentacion;
            }
        }

        $this->cambiar($solicitud, EstadoSolicitudPrestacion::Enviada, AccionEventoSolicitud::Enviada, $actor, $datos, array_filter([
            'documentos_pendientes' => $faltan ?: null,
            'justificacion' => $faltan !== [] ? trim((string) $justificacion) : null,
            'adaptador' => $solicitud->via === ViaAcceso::ExpedienteExterno ? $this->gestor->nombre() : null,
        ]));
    }

    /**
     * Punto único de las actualizaciones del expediente (RN-07): estado externo, incidencias y
     * resolución. Idempotente: la misma actualización no se registra dos veces.
     *
     * @param SolicitudPrestacion $solicitud Enviada.
     * @param ActualizacionExpediente $dto
     * @param OrigenActualizacion $origen
     * @param User|null $actor
     *
     * @return void
     *
     * @throws DomainException
     */
    public function registrarActualizacion(SolicitudPrestacion $solicitud, ActualizacionExpediente $dto, OrigenActualizacion $origen, ?User $actor = null): void
    {
        $this->exigirEstado($solicitud, [EstadoSolicitudPrestacion::Enviada], 'Solo se actualiza una solicitud enviada.');

        DB::transaction(function () use ($solicitud, $dto, $origen, $actor): void {
            if ($dto->estadoCodigo !== null || $dto->estadoNormalizado !== null) {
                $this->actualizarEstado($solicitud, $dto, $origen, $actor);
            }

            foreach ($dto->incidencias as $incidencia) {
                $this->registrarIncidencia($solicitud, $incidencia, $origen, $actor);
            }

            if ($dto->resolucion !== null) {
                $this->resoluciones->incorporar($solicitud->fresh(), $dto->resolucion, $origen, $actor);

                return;
            }

            if (in_array($solicitud->fresh()->estado_externo_normalizado, ['desistida', 'archivada'], true)) {
                $this->cerrarSinResolucion($solicitud->fresh(), 'Estado del expediente: '.($dto->estadoTexto ?? $solicitud->estado_externo_normalizado), $origen, $actor);
            }
        });
    }

    /**
     * Procesa un aviso del gestor sobre un expediente (lo usará el endpoint; hoy, el simulador).
     *
     * @param string $numeroExpediente
     * @param ActualizacionExpediente $dto
     *
     * @return void
     *
     * @throws DomainException Si no hay ninguna solicitud enviada con ese expediente.
     */
    public function procesarAviso(string $numeroExpediente, ActualizacionExpediente $dto): void
    {
        $solicitud = SolicitudPrestacion::where('numero_expediente', $numeroExpediente)->where('estado', EstadoSolicitudPrestacion::Enviada->value)->first()
            ?? throw new DomainException("No hay ninguna solicitud enviada con el expediente {$numeroExpediente}.");

        $this->registrarActualizacion($solicitud, $dto, OrigenActualizacion::AvisoGestor);
    }

    /**
     * «Consultar ahora» (RN-07.3): solo con un adaptador que admita la consulta.
     *
     * @param SolicitudPrestacion $solicitud
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function consultarAhora(SolicitudPrestacion $solicitud, User $actor): void
    {
        if (! Gate::forUser($actor)->allows('view', $solicitud)) {
            throw new AuthorizationException('No puedes consultar esta solicitud.');
        }

        if (! $this->gestor->admite(AdaptadorGestorExpedientes::CONSULTAR) || $solicitud->numero_expediente === null) {
            throw new DomainException('El estado de este expediente no se puede consultar automáticamente.');
        }

        $this->registrarActualizacion($solicitud, $this->gestor->consultarEstado($solicitud->numero_expediente), OrigenActualizacion::ConsultaManual, $actor);
    }

    /**
     * Consulta automática de los expedientes enviados (comando `expedientes:consultar`). Un fallo
     * en una solicitud no para las demás: queda en el log con la causa.
     *
     * @return array{consultadas: int, fallidas: int}
     */
    public function consultarPendientes(): array
    {
        if (! $this->gestor->admite(AdaptadorGestorExpedientes::CONSULTAR)) {
            return ['consultadas' => 0, 'fallidas' => 0];
        }

        $resultado = ['consultadas' => 0, 'fallidas' => 0];

        SolicitudPrestacion::where('estado', EstadoSolicitudPrestacion::Enviada->value)
            ->where('via', ViaAcceso::ExpedienteExterno->value)->whereNotNull('numero_expediente')
            ->orderBy('id')->each(function (SolicitudPrestacion $solicitud) use (&$resultado): void {
                try {
                    $this->registrarActualizacion($solicitud, $this->gestor->consultarEstado($solicitud->numero_expediente), OrigenActualizacion::ConsultaAutomatica);
                    $resultado['consultadas']++;
                } catch (Throwable $e) {
                    $resultado['fallidas']++;
                    Log::warning("expedientes:consultar: la solicitud {$solicitud->id} ({$solicitud->numero_expediente}) ha fallado: {$e->getMessage()}");
                }
            });

        return $resultado;
    }

    /**
     * Registro manual de estado, incidencia o resolución por tramitación (RN-07.4).
     *
     * @param SolicitudPrestacion $solicitud
     * @param ActualizacionExpediente $dto
     * @param User $actor Con `solicitud.registrar_manual` en la unidad.
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function registrarManual(SolicitudPrestacion $solicitud, ActualizacionExpediente $dto, User $actor): void
    {
        if (! Gate::forUser($actor)->allows('registrarManual', $solicitud)) {
            throw new AuthorizationException('No puedes registrar a mano el estado de esta solicitud.');
        }

        $this->registrarActualizacion($solicitud, $dto, OrigenActualizacion::RegistroManual, $actor);
    }

    /**
     * Marca una incidencia como atendida, con los documentos aportados (y se los envía al gestor si lo admite).
     *
     * @param SolicitudPrestacion $solicitud
     * @param int $incidenciaEventoId Evento `incidencia` abierto.
     * @param User $actor De tramitación de la unidad.
     * @param list<int> $documentoIds Documentos de la persona que se aportan.
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function atenderIncidencia(SolicitudPrestacion $solicitud, int $incidenciaEventoId, User $actor, array $documentoIds = []): void
    {
        $this->exigirTramitacion($solicitud, $actor);

        if (! $solicitud->incidenciasAbiertas()->contains('id', $incidenciaEventoId)) {
            throw new DomainException('Esa incidencia no está abierta.');
        }

        DB::transaction(function () use ($solicitud, $incidenciaEventoId, $actor, $documentoIds): void {
            foreach (Documento::disponibles()->whereIn('id', $documentoIds)->get() as $documento) {
                $this->vincularDocumento($solicitud, $documento, $actor);
            }

            if ($documentoIds !== [] && $solicitud->numero_expediente !== null && $this->gestor->admite(AdaptadorGestorExpedientes::APORTAR)) {
                $this->gestor->aportarDocumentacion($solicitud->numero_expediente, array_values($documentoIds));
            }

            $this->evento($solicitud, AccionEventoSolicitud::IncidenciaAtendida, $solicitud->estado, $actor, [
                'incidencia_evento_id' => $incidenciaEventoId,
                'documentos' => array_values($documentoIds),
            ]);
        });
    }

    /**
     * Anula una solicitud antes del envío, con motivo: quien la creó o tramitación de la unidad.
     *
     * @param SolicitudPrestacion $solicitud
     * @param User $actor
     * @param string $motivo
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    public function anular(SolicitudPrestacion $solicitud, User $actor, string $motivo): void
    {
        if (! $solicitud->estado->antesDelEnvio()) {
            throw new DomainException('Una solicitud enviada no se anula: se cierra cuando lo diga el expediente.');
        }

        $puede = $solicitud->creada_por_id === $actor->id
            || ($solicitud->unidad_tramitadora_id !== null && Gate::forUser($actor)->allows('tramitar', $solicitud))
            || ($actor->hasRole('supervision') && Gate::forUser($actor)->allows('view', $solicitud));
        if (! $puede) {
            throw new AuthorizationException('Solo anula la solicitud quien la creó o tramitación.');
        }

        $motivo = $this->exigirTexto($motivo, 'Indica el motivo de la anulación.');
        $this->cambiar($solicitud, EstadoSolicitudPrestacion::Anulada, AccionEventoSolicitud::Anulada, $actor, ['motivo_cierre' => $motivo, 'responsable_id' => null], [], $motivo);
    }

    /**
     * Cierra una solicitud enviada sin resolución (desistida o archivada), con motivo, y avisa al TS.
     *
     * @param SolicitudPrestacion $solicitud
     * @param string $motivo
     * @param OrigenActualizacion $origen
     * @param User|null $actor
     *
     * @return void
     *
     * @throws DomainException
     */
    public function cerrarSinResolucion(SolicitudPrestacion $solicitud, string $motivo, OrigenActualizacion $origen, ?User $actor = null): void
    {
        $this->exigirEstado($solicitud, [EstadoSolicitudPrestacion::Enviada]);
        $motivo = $this->exigirTexto($motivo, 'Indica el motivo del cierre.');

        DB::transaction(function () use ($solicitud, $motivo, $origen, $actor): void {
            $this->cambiar($solicitud, EstadoSolicitudPrestacion::CerradaSinResolucion, AccionEventoSolicitud::CerradaSinResolucion, $actor, ['motivo_cierre' => $motivo], [], $motivo, $origen);
            $this->avisar($solicitud->creada_por_id, $solicitud, 'Solicitud cerrada sin resolución', "La solicitud de {$solicitud->prestacion->nombre} se ha cerrado sin resolución: {$motivo}");
        });
    }

    /**
     * Nombres de los documentos obligatorios de la configuración que no están vinculados.
     *
     * @param SolicitudPrestacion $solicitud
     *
     * @return list<string>
     */
    public function documentosObligatoriosPendientes(SolicitudPrestacion $solicitud): array
    {
        $aportados = Documento::disponibles()->whereIn('id', $solicitud->vinculosDocumentos()->pluck('documento_id'))->pluck('tipo_documental_id')->all();

        return collect($solicitud->configuracion_snapshot['documentos'] ?? [])
            ->filter(fn (array $d) => $d['obligatorio'] && ! in_array($d['tipo_documental_id'], $aportados, true))
            ->pluck('nombre')->values()->all();
    }

    /**
     * Copia de la configuración de la prestación al crear la solicitud (RN-04).
     *
     * @param Prestacion $prestacion
     *
     * @return array<string, mixed>
     */
    private function snapshot(Prestacion $prestacion): array
    {
        return [
            'via' => $prestacion->via_acceso->value,
            'unidad_tramitadora_tipo' => $prestacion->unidad_tramitadora_tipo,
            'unidad_tramitadora_id' => $prestacion->unidad_tramitadora_id,
            'formulario_solicitud_id' => $prestacion->formulario_solicitud_id,
            'codigo_gestor' => $prestacion->codigo_gestor,
            'documentos' => $prestacion->documentosExigidos->map(fn ($d) => [
                'tipo_documental_id' => $d->tipo_documental_id,
                'nombre' => $d->tipoDocumental->nombre,
                'obligatorio' => (bool) $d->obligatorio,
            ])->values()->all(),
            'version_prestacion' => method_exists($prestacion, 'versiones') ? $prestacion->versiones()->count() : null,
        ];
    }

    /**
     * Datos que se envían al gestor.
     *
     * @param SolicitudPrestacion $solicitud
     *
     * @return SolicitudParaGestor
     */
    private function paraGestor(SolicitudPrestacion $solicitud): SolicitudParaGestor
    {
        return new SolicitudParaGestor(
            $solicitud->id,
            $solicitud->configuracion_snapshot['codigo_gestor'] ?? null,
            $solicitud->ciudadano_id,
            $solicitud->ficha?->datos ?? [],
            $solicitud->vinculosDocumentos()->pluck('documento_id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    /**
     * Registra el estado externo (idempotente por código y fecha) y avisa al TS si cambia el normalizado.
     *
     * @param SolicitudPrestacion $solicitud
     * @param ActualizacionExpediente $dto
     * @param OrigenActualizacion $origen
     * @param User|null $actor
     *
     * @return void
     */
    private function actualizarEstado(SolicitudPrestacion $solicitud, ActualizacionExpediente $dto, OrigenActualizacion $origen, ?User $actor): void
    {
        $fecha = $dto->fecha->toDateString();
        $repetida = $solicitud->eventos()->where('accion', AccionEventoSolicitud::EstadoActualizado->value)
            ->where('detalle->codigo', $dto->estadoCodigo)->where('detalle->fecha', $fecha)->exists();
        if ($repetida) {
            return;
        }

        $normalizado = $dto->estadoNormalizado
            ?? config("prestaciones.estados.{$this->gestor->nombre()}.{$dto->estadoCodigo}");
        $anterior = $solicitud->estado_externo_normalizado;

        $solicitud->update([
            'estado_externo_codigo' => $dto->estadoCodigo,
            'estado_externo_texto' => $dto->estadoTexto,
            'estado_externo_normalizado' => $normalizado,
            'ultima_actualizacion_en' => now(),
        ]);

        $this->evento($solicitud, AccionEventoSolicitud::EstadoActualizado, $solicitud->estado, $actor, [
            'codigo' => $dto->estadoCodigo, 'texto' => $dto->estadoTexto, 'normalizado' => $normalizado, 'fecha' => $fecha,
        ], null, $origen);

        if ($normalizado !== null && $normalizado !== $anterior) {
            $this->avisar($solicitud->creada_por_id, $solicitud, 'Cambio de estado del expediente', "La solicitud de {$solicitud->prestacion->nombre} está ahora «{$dto->estadoTexto}».");
        }
    }

    /**
     * Registra una incidencia (idempotente por identificador externo o por tipo, fecha y descripción)
     * y avisa al TS y a la responsable de tramitación.
     *
     * @param SolicitudPrestacion $solicitud
     * @param IncidenciaExpediente $incidencia
     * @param OrigenActualizacion $origen
     * @param User|null $actor
     *
     * @return void
     */
    private function registrarIncidencia(SolicitudPrestacion $solicitud, IncidenciaExpediente $incidencia, OrigenActualizacion $origen, ?User $actor): void
    {
        $fecha = $incidencia->fecha->toDateString();
        $existentes = $solicitud->eventos()->where('accion', AccionEventoSolicitud::Incidencia->value)->get();
        $repetida = $existentes->contains(fn (SolicitudPrestacionEvento $e) => $incidencia->idExterno !== null
            ? ($e->detalle['id_externo'] ?? null) === $incidencia->idExterno
            : ($e->detalle['tipo'] ?? null) === $incidencia->tipo && ($e->detalle['fecha'] ?? null) === $fecha && ($e->detalle['descripcion'] ?? null) === $incidencia->descripcion);
        if ($repetida) {
            return;
        }

        $this->evento($solicitud, AccionEventoSolicitud::Incidencia, $solicitud->estado, $actor, [
            'tipo' => $incidencia->tipo,
            'fecha' => $fecha,
            'descripcion' => $incidencia->descripcion,
            'plazo' => $incidencia->plazo?->toDateString(),
            'id_externo' => $incidencia->idExterno,
        ], null, $origen);

        $cuerpo = "Incidencia en la solicitud de {$solicitud->prestacion->nombre}: {$incidencia->descripcion}"
            .($incidencia->plazo ? " (plazo: {$incidencia->plazo->format('d/m/Y')})" : '').'.';
        foreach (array_unique(array_filter([$solicitud->creada_por_id, $solicitud->responsable_id])) as $usuarioId) {
            $this->avisar($usuarioId, $solicitud, 'Incidencia en un expediente', $cuerpo, TipoAlerta::Alerta);
        }
    }

    /**
     * Cambia el estado con sus datos y deja el evento.
     *
     * @param SolicitudPrestacion $solicitud
     * @param EstadoSolicitudPrestacion $nuevo
     * @param AccionEventoSolicitud $accion
     * @param User|null $actor
     * @param array<string, mixed> $datos
     * @param array<string, mixed> $detalle
     * @param string|null $nota
     * @param OrigenActualizacion $origen
     *
     * @return void
     */
    private function cambiar(SolicitudPrestacion $solicitud, EstadoSolicitudPrestacion $nuevo, AccionEventoSolicitud $accion, ?User $actor, array $datos = [], array $detalle = [], ?string $nota = null, OrigenActualizacion $origen = OrigenActualizacion::Usuario): void
    {
        DB::transaction(function () use ($solicitud, $nuevo, $accion, $actor, $datos, $detalle, $nota, $origen): void {
            $antes = $solicitud->estado;
            $solicitud->update($datos + ['estado' => $nuevo]);
            $this->evento($solicitud, $accion, $antes, $actor, $detalle, $nota, $origen);
        });
    }

    /**
     * Inserta un evento en el historial.
     *
     * @param SolicitudPrestacion $solicitud
     * @param AccionEventoSolicitud $accion
     * @param EstadoSolicitudPrestacion|null $antes
     * @param User|null $actor
     * @param array<string, mixed> $detalle
     * @param string|null $nota
     * @param OrigenActualizacion $origen
     *
     * @return void
     */
    private function evento(SolicitudPrestacion $solicitud, AccionEventoSolicitud $accion, ?EstadoSolicitudPrestacion $antes, ?User $actor, array $detalle = [], ?string $nota = null, OrigenActualizacion $origen = OrigenActualizacion::Usuario): void
    {
        SolicitudPrestacionEvento::create([
            'solicitud_id' => $solicitud->id,
            'accion' => $accion,
            'estado_antes' => $antes,
            'estado_despues' => $solicitud->estado,
            'origen' => $actor === null && $origen === OrigenActualizacion::Usuario ? OrigenActualizacion::Sistema : $origen,
            'usuario_id' => $actor?->id,
            'detalle' => $detalle ?: null,
            'nota' => $nota,
        ]);
    }

    /**
     * Aviso a un profesional sobre la solicitud (sin nombrar a la persona; nunca al ciudadano, RN-06).
     *
     * @param int $usuarioId
     * @param SolicitudPrestacion $solicitud
     * @param string $titulo
     * @param string $cuerpo
     * @param TipoAlerta $tipo
     *
     * @return void
     */
    private function avisar(int $usuarioId, SolicitudPrestacion $solicitud, string $titulo, string $cuerpo, TipoAlerta $tipo = TipoAlerta::Aviso): void
    {
        $this->alertas->crear([
            'tipo' => $tipo,
            'origen_type' => SolicitudPrestacion::class,
            'origen_id' => $solicitud->id,
            'titulo' => $titulo,
            'cuerpo' => $cuerpo,
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Quién edita datos, personas y sugerencia: en borrador, quien la creó (o puede crear y ve la
     * historia); en preparación, la responsable.
     *
     * @param SolicitudPrestacion $solicitud
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    private function exigirEdicion(SolicitudPrestacion $solicitud, User $actor): void
    {
        if ($solicitud->estado === EstadoSolicitudPrestacion::Borrador) {
            if (! Gate::forUser($actor)->allows('editarBorrador', $solicitud)) {
                throw new AuthorizationException('Solo quien prepara la solicitud la edita en borrador.');
            }

            return;
        }

        $this->exigirResponsable($solicitud, $actor);
    }

    /**
     * Quién vincula documentos: lo mismo que para editar y, enviada, tramitación (incidencias).
     *
     * @param SolicitudPrestacion $solicitud
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    private function exigirDocumentos(SolicitudPrestacion $solicitud, User $actor): void
    {
        if ($solicitud->estado === EstadoSolicitudPrestacion::Enviada) {
            $this->exigirTramitacion($solicitud, $actor);

            return;
        }

        $this->exigirEdicion($solicitud, $actor);
    }

    /**
     * Exige que el usuario tramite la unidad de la solicitud.
     *
     * @param SolicitudPrestacion $solicitud
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException
     */
    private function exigirTramitacion(SolicitudPrestacion $solicitud, User $actor): void
    {
        if (! Gate::forUser($actor)->allows('tramitar', $solicitud)) {
            throw new AuthorizationException('Esta solicitud no está en la bandeja de tu unidad.');
        }
    }

    /**
     * Exige que el usuario sea la responsable de la solicitud en preparación.
     *
     * @param SolicitudPrestacion $solicitud
     * @param User $actor
     *
     * @return void
     *
     * @throws AuthorizationException|DomainException
     */
    private function exigirResponsable(SolicitudPrestacion $solicitud, User $actor): void
    {
        $this->exigirEstado($solicitud, [EstadoSolicitudPrestacion::EnPreparacion], 'La solicitud no está en preparación.');
        $this->exigirTramitacion($solicitud, $actor);

        if ($solicitud->responsable_id !== $actor->id) {
            throw new AuthorizationException('La solicitud la tiene tomada otra persona.');
        }
    }

    /**
     * Rechaza la operación si la solicitud no está en uno de los estados dados.
     *
     * @param SolicitudPrestacion $solicitud
     * @param list<EstadoSolicitudPrestacion> $estados
     * @param string|null $mensaje
     *
     * @return void
     *
     * @throws DomainException
     */
    private function exigirEstado(SolicitudPrestacion $solicitud, array $estados, ?string $mensaje = null): void
    {
        if (! in_array($solicitud->estado, $estados, true)) {
            throw new DomainException($mensaje ?? "La solicitud está {$solicitud->estado->label()}.");
        }
    }

    /**
     * Devuelve el texto recortado o rechaza uno vacío.
     *
     * @param string $texto
     * @param string $mensaje
     *
     * @return string
     *
     * @throws DomainException
     */
    private function exigirTexto(string $texto, string $mensaje): string
    {
        $texto = trim($texto);

        if ($texto === '') {
            throw new DomainException($mensaje);
        }

        return $texto;
    }
}
