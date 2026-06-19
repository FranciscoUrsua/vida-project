<?php

namespace Modules\Intervencion\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Prestaciones\Models\Prestacion;

/**
 * Actuación comprometida por el Ayuntamiento en un Plan de Intervención.
 *
 * Regla de negocio: toda actuación del Ayuntamiento debe estar vinculada a
 * una prestación del catálogo. Sin prestación_id no puede guardarse.
 *
 * @property int $id
 * @property int $plan_id
 * @property int $prestacion_id Obligatorio — regla de negocio en booted()
 * @property string|null $descripcion_especifica
 * @property int|null $responsable_id
 * @property string $estado 'pendiente' | 'en_curso' | 'completada' | 'cancelada'
 * @property Carbon|null $fecha_inicio_prevista
 * @property Carbon|null $fecha_fin_prevista
 * @property Carbon|null $fecha_fin_real
 * @property int $orden
 */
class PlanActuacionAyuntamiento extends Model
{
    protected $table = 'plan_actuaciones_ayuntamiento';

    protected $fillable = [
        'plan_id', 'prestacion_id', 'descripcion_especifica', 'responsable_id',
        'estado', 'fecha_inicio_prevista', 'fecha_fin_prevista', 'fecha_fin_real', 'orden',
    ];

    protected $casts = [
        'fecha_inicio_prevista' => 'date',
        'fecha_fin_prevista' => 'date',
        'fecha_fin_real' => 'date',
    ];

    // -------------------------------------------------------------------------
    // Hooks
    // -------------------------------------------------------------------------

    protected static function booted(): void
    {
        static::saving(function (self $actuacion): void {
            if (empty($actuacion->prestacion_id)) {
                throw new \LogicException(
                    'Las actuaciones del Ayuntamiento deben estar vinculadas a una prestación del catálogo.'
                );
            }
        });
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Plan al que pertenece esta actuación.
     *
     * @return BelongsTo<PlanDeIntervencion, self>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_id');
    }

    /**
     * Prestación del catálogo vinculada.
     *
     * @return BelongsTo<Prestacion, self>
     */
    public function prestacion(): BelongsTo
    {
        return $this->belongsTo(Prestacion::class, 'prestacion_id');
    }

    /**
     * Profesional responsable de ejecutar esta actuación.
     *
     * @return BelongsTo<User, self>
     */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }
}
