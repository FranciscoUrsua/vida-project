<?php

namespace Modules\Intervencion\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Centro\Models\Servicio;

/**
 * Profesional participante en un Plan de Intervención distinto del responsable.
 *
 * Permite registrar la participación de otros profesionales (educadores sociales,
 * psicólogos, etc.) con su rol específico en el plan y, opcionalmente, su
 * servicio de origen cuando proceden de atención especializada.
 *
 * @property int $id
 * @property int $plan_id
 * @property int $user_id
 * @property string $rol_en_plan
 * @property int|null $servicio_id
 * @property \Carbon\Carbon $fecha_inicio
 * @property \Carbon\Carbon|null $fecha_fin
 */
class PlanParticipante extends Model
{
    protected $table = 'plan_participantes';

    protected $fillable = [
        'plan_id', 'user_id', 'rol_en_plan', 'servicio_id', 'fecha_inicio', 'fecha_fin',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Plan al que pertenece este participante.
     *
     * @return BelongsTo<PlanDeIntervencion, self>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_id');
    }

    /**
     * Usuario profesional participante.
     *
     * @return BelongsTo<User, self>
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Servicio de origen del participante, si aplica.
     *
     * @return BelongsTo<Servicio, self>
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Indica si la participación está activa (sin fecha de fin o con fecha futura).
     *
     * @return bool
     */
    public function estaActivo(): bool
    {
        return $this->fecha_fin === null || $this->fecha_fin->isFuture();
    }
}
