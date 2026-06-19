<?php

namespace Modules\Intervencion\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de un cambio en el contenido de un Plan de Intervención.
 *
 * Cubre cualquier modificación al plan activo, tanto discrecional (TSR)
 * como derivada de un seguimiento. El snapshot almacena el estado completo
 * del plan ANTES del cambio para permitir reconstrucción histórica.
 *
 * Nota: revisiones_plan cubre los cambios de versión formales (nueva firma).
 * plan_cambios cubre el historial completo incluyendo cambios menores.
 *
 * @property int $id
 * @property int $plan_id
 * @property int $version
 * @property int $profesional_id
 * @property string $origen 'discrecional' | 'seguimiento'
 * @property int|null $seguimiento_id
 * @property string $motivo
 * @property array $snapshot Estado completo del plan antes del cambio
 * @property Carbon $created_at
 */
class PlanCambio extends Model
{
    public $timestamps = false;

    protected $table = 'plan_cambios';

    protected $fillable = [
        'plan_id', 'version', 'profesional_id', 'origen',
        'seguimiento_id', 'motivo', 'snapshot', 'created_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Plan al que pertenece este registro de cambio.
     *
     * @return BelongsTo<PlanDeIntervencion, self>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_id');
    }

    /**
     * Profesional que realizó el cambio.
     *
     * @return BelongsTo<User, self>
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_id');
    }

    /**
     * Seguimiento que originó el cambio, si aplica.
     *
     * @return BelongsTo<SeguimientoPlan, self>
     */
    public function seguimiento(): BelongsTo
    {
        return $this->belongsTo(SeguimientoPlan::class, 'seguimiento_id');
    }
}
