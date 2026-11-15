<?php

namespace Modules\Prestaciones\Models;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Centro\Models\Centro;
use Modules\Documentos\Models\DocumentoVinculo;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\PlanObjetivo;
use Modules\Prestaciones\Enums\AccionEventoSolicitud;
use Modules\Prestaciones\Enums\EstadoSolicitudPrestacion;
use Modules\Prestaciones\Enums\ViaAcceso;

/**
 * Solicitud de una prestación con vía de tramitación (docs/modulo-solicitudes-expedientes.md §2.2).
 *
 * El trabajador social la prepara y la pasa a la bandeja de la unidad tramitadora;
 * tramitación la toma, completa y envía; las actualizaciones del expediente, la resolución y
 * el cierre quedan en su historial inmutable (`solicitud_prestacion_eventos`). Conserva en
 * `configuracion_snapshot` la configuración de la prestación al crearse (RN-04). Solo la
 * escribe SolicitudPrestacionService.
 *
 * Sin scope de ámbito: la bandeja de tramitación ve solicitudes de personas de otras UO; el
 * acceso lo deciden SolicitudPrestacionPolicy y las consultas de la bandeja (RN-10).
 *
 * @property int $id
 * @property int $ciudadano_id
 * @property int $historia_id
 * @property int $prestacion_id
 * @property array<string, mixed> $configuracion_snapshot
 * @property ViaAcceso $via
 * @property int|null $plan_id
 * @property int|null $plan_objetivo_id
 * @property int|null $seguimiento_id
 * @property int $centro_solicitud_id
 * @property int|null $unidad_tramitadora_id
 * @property int $creada_por_id
 * @property int|null $sugerida_id
 * @property int|null $responsable_id
 * @property EstadoSolicitudPrestacion $estado
 * @property int|null $ficha_id
 * @property string|null $numero_expediente
 * @property Carbon|null $fecha_envio
 * @property string|null $estado_externo_codigo
 * @property string|null $estado_externo_texto
 * @property string|null $estado_externo_normalizado
 * @property Carbon|null $ultima_actualizacion_en
 * @property string|null $motivo_cierre Cifrado.
 * @property-read Prestacion $prestacion
 * @property-read Ciudadano $ciudadano
 * @property-read Ficha|null $ficha
 * @property-read ResolucionSolicitud|null $resolucion
 */
class SolicitudPrestacion extends Model
{
    use Auditable;
    use SoftDeletes;

    /** @var string */
    protected $table = 'solicitudes_prestacion';

    /** @var list<string> */
    protected $fillable = [
        'ciudadano_id', 'historia_id', 'prestacion_id', 'configuracion_snapshot', 'via', 'plan_id', 'plan_objetivo_id',
        'seguimiento_id', 'centro_solicitud_id', 'unidad_tramitadora_id', 'creada_por_id', 'sugerida_id', 'responsable_id',
        'estado', 'ficha_id', 'numero_expediente', 'fecha_envio', 'estado_externo_codigo', 'estado_externo_texto',
        'estado_externo_normalizado', 'ultima_actualizacion_en', 'motivo_cierre',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'configuracion_snapshot' => 'array',
        'via' => ViaAcceso::class,
        'estado' => EstadoSolicitudPrestacion::class,
        'fecha_envio' => 'date',
        'ultima_actualizacion_en' => 'datetime',
        'motivo_cierre' => 'encrypted',
    ];

    /**
     * Ciudadano titular, para la auditoría.
     *
     * @return int|null
     */
    public function getCiudadanoId(): ?int
    {
        return $this->ciudadano_id;
    }

    /**
     * Prestación solicitada.
     *
     * @return BelongsTo<Prestacion, $this>
     */
    public function prestacion(): BelongsTo
    {
        return $this->belongsTo(Prestacion::class, 'prestacion_id')->withTrashed();
    }

    /**
     * Persona titular (sin scope: el acceso lo decide la policy).
     *
     * @return BelongsTo<Ciudadano, $this>
     */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id')->withoutGlobalScopes();
    }

    /**
     * Historia Social de la persona (sin scope).
     *
     * @return BelongsTo<HistoriaSocial, $this>
     */
    public function historia(): BelongsTo
    {
        return $this->belongsTo(HistoriaSocial::class, 'historia_id')->withoutGlobalScopes();
    }

    /**
     * Plan desde el que se solicita, si lo hay.
     *
     * @return BelongsTo<PlanDeIntervencion, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_id')->withoutGlobalScopes();
    }

    /**
     * Objetivo del plan desde el que se solicita, si lo hay.
     *
     * @return BelongsTo<PlanObjetivo, $this>
     */
    public function objetivo(): BelongsTo
    {
        return $this->belongsTo(PlanObjetivo::class, 'plan_objetivo_id');
    }

    /**
     * Centro desde el que se crea.
     *
     * @return BelongsTo<Centro, $this>
     */
    public function centroSolicitud(): BelongsTo
    {
        return $this->belongsTo(Centro::class, 'centro_solicitud_id');
    }

    /**
     * Unidad que la tramita (resuelta al pasar a bandeja).
     *
     * @return BelongsTo<UnidadOrganizativa, $this>
     */
    public function unidadTramitadora(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizativa::class, 'unidad_tramitadora_id');
    }

    /**
     * Trabajador social que la crea.
     *
     * @return BelongsTo<User, $this>
     */
    public function creadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creada_por_id')->withTrashed();
    }

    /**
     * Persona de tramitación sugerida (no es asignación, RN-03).
     *
     * @return BelongsTo<User, $this>
     */
    public function sugerida(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sugerida_id')->withTrashed();
    }

    /**
     * Quien la tiene tomada.
     *
     * @return BelongsTo<User, $this>
     */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id')->withTrashed();
    }

    /**
     * Datos del formulario como ficha (con copia del esquema).
     *
     * @return BelongsTo<Ficha, $this>
     */
    public function ficha(): BelongsTo
    {
        return $this->belongsTo(Ficha::class, 'ficha_id');
    }

    /**
     * Otras personas de la unidad de convivencia afectadas.
     *
     * @return BelongsToMany<Ciudadano, $this>
     */
    public function personasAfectadas(): BelongsToMany
    {
        return $this->belongsToMany(Ciudadano::class, 'solicitud_prestacion_personas', 'solicitud_id', 'ciudadano_id')->withoutGlobalScopes();
    }

    /**
     * Historial, del más antiguo al más reciente.
     *
     * @return HasMany<SolicitudPrestacionEvento, $this>
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(SolicitudPrestacionEvento::class, 'solicitud_id')->orderBy('id');
    }

    /**
     * Resolución incorporada, si la hay.
     *
     * @return HasOne<ResolucionSolicitud, $this>
     */
    public function resolucion(): HasOne
    {
        return $this->hasOne(ResolucionSolicitud::class, 'solicitud_id');
    }

    /**
     * Vínculos activos de documentos a la solicitud.
     *
     * @return MorphMany<DocumentoVinculo, $this>
     */
    public function vinculosDocumentos(): MorphMany
    {
        return $this->morphMany(DocumentoVinculo::class, 'vinculable')->where('activo', true);
    }

    /**
     * Incidencias abiertas: eventos `incidencia` sin un `incidencia_atendida` que los referencie.
     *
     * @return Collection<int, SolicitudPrestacionEvento>
     */
    public function incidenciasAbiertas(): Collection
    {
        $eventos = $this->eventos()->get();
        $atendidas = $eventos->where('accion', AccionEventoSolicitud::IncidenciaAtendida)
            ->map(fn (SolicitudPrestacionEvento $e) => (int) ($e->detalle['incidencia_evento_id'] ?? 0))
            ->all();

        return $eventos->where('accion', AccionEventoSolicitud::Incidencia)
            ->reject(fn (SolicitudPrestacionEvento $e) => in_array($e->id, $atendidas, true))
            ->values();
    }

    /**
     * Solo solicitudes con alguna incidencia abierta.
     *
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeConIncidenciaAbierta(Builder $query): Builder
    {
        return $query->whereExists(fn ($q) => $q->selectRaw('1')->from('solicitud_prestacion_eventos as i')
            ->whereColumn('i.solicitud_id', 'solicitudes_prestacion.id')
            ->where('i.accion', AccionEventoSolicitud::Incidencia->value)
            ->whereNotExists(fn ($a) => $a->selectRaw('1')->from('solicitud_prestacion_eventos as a')
                ->whereColumn('a.solicitud_id', 'i.solicitud_id')
                ->where('a.accion', AccionEventoSolicitud::IncidenciaAtendida->value)
                ->whereRaw("(a.detalle->>'incidencia_evento_id')::bigint = i.id")));
    }
}
