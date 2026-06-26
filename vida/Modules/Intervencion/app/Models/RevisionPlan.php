<?php

namespace Modules\Intervencion\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Registro histórico de una revisión de Plan de Intervención.
 *
 * Almacena qué versión fue revisada, por quién, cuándo y por qué motivo.
 * Si la revisión tiene origen en un seguimiento concreto, se registra el vínculo.
 *
 * @property int $id
 * @property int $plan_id
 * @property int $version_anterior
 * @property int $version_nueva
 * @property int $profesional_id
 * @property Carbon $fecha
 * @property string $motivo_revision
 * @property int|null $seguimiento_id
 */
class RevisionPlan extends Model
{
    protected $table = 'revisiones_plan';

    protected $fillable = [
        'plan_id',
        'version_anterior',
        'version_nueva',
        'profesional_id',
        'fecha',
        'motivo_revision',
        'seguimiento_id',
    ];

    protected $casts = [
        'version_anterior' => 'integer',
        'version_nueva' => 'integer',
        'fecha' => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * @return BelongsTo<PlanDeIntervencion, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_id');
    }

    /**
     * @return BelongsTo<SeguimientoPlan, $this>
     */
    public function seguimiento(): BelongsTo
    {
        return $this->belongsTo(SeguimientoPlan::class, 'seguimiento_id');
    }
}
