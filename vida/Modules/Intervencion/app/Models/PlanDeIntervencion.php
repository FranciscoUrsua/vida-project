<?php

namespace Modules\Intervencion\Models;

use App\Models\HistoriaSocial;
use App\Models\Scopes\AmbitoUoScope;
use App\Traits\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
 * @property TipoPlan $tipo
 * @property int|null $servicio_especializado_id
 * @property int $profesional_responsable_id
 * @property int|null $plan_asp_id
 * @property EstadoPlan $estado
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
        'tipo',
        'servicio_especializado_id',
        'profesional_responsable_id',
        'plan_asp_id',
        'estado',
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
     * @return BelongsTo<HistoriaSocial, PlanDeIntervencion>
     */
    /** @inheritDoc */
    public function getCiudadanoId(): ?int
    {
        return $this->historia()->withoutGlobalScopes()->value('ciudadano_id');
    }

    public function historia(): BelongsTo
    {
        return $this->belongsTo(HistoriaSocial::class, 'historia_id');
    }

    /**
     * @return BelongsTo<User, PlanDeIntervencion>
     */
    public function profesionalResponsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_responsable_id');
    }

    /**
     * Plan general ASP al que está vinculado este plan especializado.
     *
     * @return BelongsTo<PlanDeIntervencion, PlanDeIntervencion>
     */
    public function planAsp(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_asp_id');
    }

    /**
     * Planes especializados vinculados a este plan general.
     *
     * @return HasMany<PlanDeIntervencion>
     */
    public function planesEspecializados(): HasMany
    {
        return $this->hasMany(PlanDeIntervencion::class, 'plan_asp_id');
    }

    /**
     * @return HasMany<FirmaPlan>
     */
    public function firmas(): HasMany
    {
        return $this->hasMany(FirmaPlan::class, 'plan_id');
    }

    /**
     * @return HasMany<RevisionPlan>
     */
    public function revisiones(): HasMany
    {
        return $this->hasMany(RevisionPlan::class, 'plan_id');
    }

    /**
     * @return HasMany<SeguimientoPlan>
     */
    public function seguimientos(): HasMany
    {
        return $this->hasMany(SeguimientoPlan::class, 'plan_id');
    }

    /**
     * @return HasMany<Apunte>
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
            ->whereNotNull('firma_ciudadano')
            ->whereNotNull('firma_profesional')
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
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Solo planes activos.
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', EstadoPlan::Activo);
    }
}
