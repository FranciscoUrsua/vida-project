<?php

namespace Modules\Intervencion\Models;

use App\Models\HistoriaSocial;
use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Modules\Intervencion\Database\Factories\EntrevistaFactory;
use Modules\Intervencion\Enums\TipoEntrevista;

/**
 * Entrevista profesional-ciudadano.
 *
 * Contenedor de trabajo del profesional durante y después del encuentro.
 * Puede existir sin cita previa (visitas domiciliarias, contactos urgentes).
 * Puede generar una Valoracion o un SeguimientoPlan según su tipo.
 *
 * @property int $id
 * @property int $historia_id
 * @property int $profesional_id
 * @property int|null $cita_id
 * @property int|null $plan_intervencion_id
 * @property Carbon $fecha_hora
 * @property string $modalidad
 * @property TipoEntrevista $tipo
 * @property string|null $notas_generales
 * @property string $estado
 */
class Entrevista extends Model
{
    use Auditable;
    use HasFactory;

    protected static function newFactory(): EntrevistaFactory
    {
        return EntrevistaFactory::new();
    }

    protected $table = 'entrevistas';

    protected $fillable = [
        'historia_id',
        'profesional_id',
        'cita_id',
        'plan_intervencion_id',
        'fecha_hora',
        'modalidad',
        'tipo',
        'notas_generales',
        'estado',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'tipo' => TipoEntrevista::class,
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * @return BelongsTo<HistoriaSocial, Entrevista>
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
     * @return BelongsTo<User, Entrevista>
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_id');
    }

    /**
     * Plan de intervención al que está vinculada esta entrevista de seguimiento.
     *
     * @return BelongsTo<PlanDeIntervencion, Entrevista>
     */
    public function planDeIntervencion(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_intervencion_id');
    }

    /**
     * Valoración generada en esta entrevista (si la hubiera).
     *
     * @return HasOne<Valoracion>
     */
    public function valoracion(): HasOne
    {
        return $this->hasOne(Valoracion::class, 'entrevista_id');
    }

    /**
     * Seguimiento del plan generado en esta entrevista (si la hubiera).
     *
     * @return HasOne<SeguimientoPlan>
     */
    public function seguimientoPlan(): HasOne
    {
        return $this->hasOne(SeguimientoPlan::class, 'entrevista_id');
    }
}
