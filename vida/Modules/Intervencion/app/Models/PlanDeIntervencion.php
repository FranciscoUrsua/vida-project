<?php

namespace Modules\Intervencion\Models;

use App\Models\HistoriaSocial;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Ciudadania\Models\UnidadConvivencia;
use Modules\Intervencion\Database\Factories\PlanDeIntervencionFactory;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Enums\MotivoCierre;
use Modules\Intervencion\Enums\TipoPlan;

/**
 * Plan de Intervención Social (PISO).
 *
 * Acuerdo formal entre el profesional y el ciudadano con objetivos,
 * prestaciones comprometidas y compromisos del ciudadano. Requiere firma
 * de ambas partes para activarse (ver estaFirmado()).
 *
 * El versionado es no destructivo: crearNuevaVersion() genera un nuevo
 * registro con version+1; el original pasa a estado en_revision.
 *
 * @property int $id
 * @property int $historia_id
 * @property int|null $tipo_plan_id
 * @property int|null $unidad_convivencia_id
 * @property TipoPlan $tipo
 * @property int|null $servicio_especializado_id
 * @property int $profesional_responsable_id
 * @property int|null $plan_asp_id
 * @property EstadoPlan $estado
 * @property string|null $diagnostico_social
 * @property string $periodicidad_seguimiento
 * @property Carbon $fecha_inicio
 * @property Carbon|null $fecha_firma
 * @property Carbon|null $fecha_cierre
 * @property MotivoCierre|null $motivo_cierre
 * @property string|null $objetivos
 * @property int $version
 */
class PlanDeIntervencion extends Model
{
    use Auditable;
    use HasFactory;
    use SoftDeletes;

    protected static function newFactory(): PlanDeIntervencionFactory
    {
        return PlanDeIntervencionFactory::new();
    }

    /**
     * Columna de FK a historias_sociales usada por AmbitoUoScope.
     * El ámbito de UO se resuelve vía Historia Social del plan.
     */
    public string $ambitoHistoriaColumn = 'historia_id';

    protected $table = 'planes_intervencion';

    protected $fillable = [
        'historia_id',
        'tipo_plan_id',
        'unidad_convivencia_id',
        'tipo',
        'servicio_especializado_id',
        'profesional_responsable_id',
        'plan_asp_id',
        'estado',
        'diagnostico_social',
        'periodicidad_seguimiento',
        'fecha_inicio',
        'fecha_firma',
        'fecha_cierre',
        'motivo_cierre',
        'objetivos',
        'version',
    ];

    protected $casts = [
        'tipo' => TipoPlan::class,
        'estado' => EstadoPlan::class,
        'motivo_cierre' => MotivoCierre::class,
        'fecha_inicio' => 'date',
        'fecha_firma' => 'date',
        'fecha_cierre' => 'date',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        // Registrar el Global Scope de ámbito de UO para filtrado automático.
        static::addGlobalScope(new AmbitoUoScope);

        // Impide activar un plan sin firma cuando se ACTUALIZA el estado a activo.
        // No aplica al crear un plan nuevo directamente con estado=activo
        // (esa protección corresponde al flujo de UI o a la lógica de firma explícita).
        static::saving(function (PlanDeIntervencion $plan) {
            if ($plan->exists && $plan->isDirty('estado') && $plan->estado === EstadoPlan::Activo) {
                if (! $plan->estaFirmado()) {
                    throw new \DomainException(
                        'Un plan no puede activarse sin firma de ambas partes (ciudadano y profesional).'
                    );
                }
            }
        });
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Devuelve el ciudadano asociado a la historia social del plan.
     */
    public function getCiudadanoId(): ?int
    {
        return $this->historia()->withoutGlobalScopes()->value('ciudadano_id');
    }

    /**
     * @return BelongsTo<HistoriaSocial, $this>
     */
    public function historia(): BelongsTo
    {
        return $this->belongsTo(HistoriaSocial::class, 'historia_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function profesionalResponsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_responsable_id');
    }

    /**
     * Plan general ASP al que está vinculado este plan especializado.
     *
     * @return BelongsTo<PlanDeIntervencion, $this>
     */
    public function planAsp(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_asp_id');
    }

    /**
     * Planes especializados vinculados a este plan general.
     *
     * @return HasMany<PlanDeIntervencion, $this>
     */
    public function planesEspecializados(): HasMany
    {
        return $this->hasMany(PlanDeIntervencion::class, 'plan_asp_id');
    }

    /**
     * @return HasMany<FirmaPlan, $this>
     */
    public function firmas(): HasMany
    {
        return $this->hasMany(FirmaPlan::class, 'plan_id');
    }

    /**
     * @return HasMany<RevisionPlan, $this>
     */
    public function revisiones(): HasMany
    {
        return $this->hasMany(RevisionPlan::class, 'plan_id');
    }

    /**
     * @return HasMany<SeguimientoPlan, $this>
     */
    public function seguimientos(): HasMany
    {
        return $this->hasMany(SeguimientoPlan::class, 'plan_id');
    }

    /**
     * @return HasMany<Apunte, $this>
     */
    public function apuntes(): HasMany
    {
        return $this->hasMany(Apunte::class, 'plan_id');
    }

    // -------------------------------------------------------------------------
    // Métodos de dominio
    // -------------------------------------------------------------------------

    /**
     * Comprueba si la versión actual del plan tiene ambas firmas registradas.
     *
     * Consulta firmas_plan filtrando por el plan_id actual Y la version actual
     * para garantizar que la firma de una versión anterior no valide la nueva.
     */
    public function estaFirmado(): bool
    {
        return FirmaPlan::where('plan_id', $this->id)
            ->where('version', $this->version)
            ->where('profesional_firmado', true)
            ->where('ciudadano_firmado', true)
            ->exists();
    }

    /**
     * Crea una nueva versión del plan a partir del estado actual.
     *
     * El plan original pasa a estado en_revision. Se crea un nuevo registro
     * con version+1 y estado borrador. Se registra la revisión en revisiones_plan.
     *
     * @param string $motivo Motivo de la revisión
     * @param int $profesionalId ID del profesional que inicia la revisión
     * @param int|null $seguimientoId Seguimiento de origen si aplica
     *
     * @return static El nuevo plan (nueva versión)
     *
     * @throws \DomainException Si el plan no está en estado activo
     */
    public function crearNuevaVersion(string $motivo, int $profesionalId, ?int $seguimientoId = null): static
    {
        return DB::transaction(function () use ($motivo, $profesionalId, $seguimientoId) {
            $versionAnterior = $this->version;

            // Archivar el plan actual como en_revision (sin pasar por el guard de firma)
            $this->timestamps = false;
            DB::table('planes_intervencion')
                ->where('id', $this->id)
                ->update(['estado' => EstadoPlan::EnRevision->value]);
            $this->timestamps = true;

            // Crear nuevo registro como siguiente versión
            $nuevoPlan = $this->replicate(['deleted_at']);
            $nuevoPlan->version = $versionAnterior + 1;
            $nuevoPlan->estado = EstadoPlan::Borrador;
            $nuevoPlan->fecha_firma = null;
            $nuevoPlan->fecha_cierre = null;
            $nuevoPlan->motivo_cierre = null;
            $nuevoPlan->saveQuietly();

            // Registrar la revisión
            RevisionPlan::create([
                'plan_id' => $this->id,
                'version_anterior' => $versionAnterior,
                'version_nueva' => $nuevoPlan->version,
                'profesional_id' => $profesionalId,
                'fecha' => now()->toDateString(),
                'motivo_revision' => $motivo,
                'seguimiento_id' => $seguimientoId,
            ]);

            return $nuevoPlan;
        });
    }

    // -------------------------------------------------------------------------
    // Relaciones — contenido del plan
    // -------------------------------------------------------------------------

    /**
     * Tipo de plan del catálogo al que pertenece.
     *
     * Nota: se usa FQCN para evitar colisión con el enum TipoPlan importado.
     *
     * @return BelongsTo<\Modules\Intervencion\Models\TipoPlan, $this>
     */
    public function tipoPlan(): BelongsTo
    {
        return $this->belongsTo(\Modules\Intervencion\Models\TipoPlan::class, 'tipo_plan_id');
    }

    /**
     * Unidad de convivencia vinculada al plan, si es un plan familiar.
     *
     * @return BelongsTo<UnidadConvivencia, $this>
     */
    public function unidadConvivencia(): BelongsTo
    {
        return $this->belongsTo(
            UnidadConvivencia::class,
            'unidad_convivencia_id'
        );
    }

    /**
     * Objetivos del plan ordenados.
     *
     * @return HasMany<PlanObjetivo, $this>
     */
    public function objetivos(): HasMany
    {
        return $this->hasMany(PlanObjetivo::class, 'plan_id')->orderBy('orden');
    }

    /**
     * Solo los objetivos generales del plan (nivel = 'general').
     *
     * @return HasMany<PlanObjetivo, $this>
     */
    public function objetivosGenerales(): HasMany
    {
        return $this->objetivos()->where('nivel', 'general');
    }

    /**
     * Solo los objetivos específicos del plan (nivel = 'especifico').
     * Son independientes de los generales; se vinculan a un área temática (tipo_ficha_id).
     *
     * @return HasMany<PlanObjetivo, $this>
     */
    public function objetivosEspecificos(): HasMany
    {
        return $this->objetivos()->where('nivel', 'especifico');
    }

    /**
     * Actuaciones del Ayuntamiento en el plan.
     *
     * @return HasMany<PlanActuacionAyuntamiento, $this>
     */
    public function actuacionesAyuntamiento(): HasMany
    {
        return $this->hasMany(PlanActuacionAyuntamiento::class, 'plan_id')->orderBy('orden');
    }

    /**
     * Compromisos del ciudadano en el plan.
     *
     * @return HasMany<PlanActuacionCiudadano, $this>
     */
    public function actuacionesCiudadano(): HasMany
    {
        return $this->hasMany(PlanActuacionCiudadano::class, 'plan_id')->orderBy('orden');
    }

    /**
     * Todos los profesionales participantes.
     *
     * @return HasMany<PlanParticipante, $this>
     */
    public function participantes(): HasMany
    {
        return $this->hasMany(PlanParticipante::class, 'plan_id');
    }

    /**
     * Solo los participantes activos (sin fecha_fin).
     *
     * @return HasMany<PlanParticipante, $this>
     */
    public function participantesActivos(): HasMany
    {
        return $this->participantes()->whereNull('fecha_fin');
    }

    /**
     * Fichas de valoración incluidas en el diagnóstico social del plan.
     *
     * @return HasMany<PlanFichaDiagnostico, $this>
     */
    public function fichasDiagnostico(): HasMany
    {
        return $this->hasMany(PlanFichaDiagnostico::class, 'plan_id')->orderBy('orden');
    }

    /**
     * Historial de cambios del plan, más reciente primero.
     *
     * @return HasMany<PlanCambio, $this>
     */
    public function cambios(): HasMany
    {
        return $this->hasMany(PlanCambio::class, 'plan_id')->orderByDesc('created_at');
    }

    // -------------------------------------------------------------------------
    // Métodos de dominio — historial de cambios
    // -------------------------------------------------------------------------

    /**
     * Registra un cambio en el historial con snapshot del estado previo.
     * Debe llamarse ANTES de aplicar los cambios.
     *
     * @param string $origen 'discrecional' | 'seguimiento'
     */
    public function registrarCambio(
        int $profesionalId,
        string $motivo,
        string $origen = 'discrecional',
        ?int $seguimientoId = null
    ): PlanCambio {
        $snapshot = [
            'diagnostico_social' => $this->diagnostico_social,
            'periodicidad_seguimiento' => $this->periodicidad_seguimiento,
            'objetivos' => $this->objetivos()->get()->toArray(),
            'actuaciones_ayuntamiento' => $this->actuacionesAyuntamiento()->with('prestacion')->get()->toArray(),
            'actuaciones_ciudadano' => $this->actuacionesCiudadano()->get()->toArray(),
            'participantes' => $this->participantesActivos()->with('profesional')->get()->toArray(),
        ];

        return $this->cambios()->create([
            'version' => $this->version,
            'profesional_id' => $profesionalId,
            'origen' => $origen,
            'seguimiento_id' => $seguimientoId,
            'motivo' => $motivo,
            'snapshot' => $snapshot,
            'created_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Solo planes activos.
     *
     * @param Builder<PlanDeIntervencion> $query
     *
     * @return Builder<PlanDeIntervencion>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', EstadoPlan::Activo);
    }
}
