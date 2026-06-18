<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Prestaciones\Models\Prestacion;

/**
 * Compromiso asumido por el ciudadano en un Plan de Intervención.
 *
 * El campo primario es descripcion (texto libre). Puede vincularse opcionalmente
 * a una prestación del catálogo cuando el compromiso corresponde a participación
 * en un recurso (taller, curso, etc.).
 *
 * @property int $id
 * @property int $plan_id
 * @property string $descripcion  Campo primario, texto libre
 * @property int|null $prestacion_id  Opcional
 * @property string $estado  'pendiente' | 'en_curso' | 'completada' | 'cancelada'
 * @property \Carbon\Carbon|null $fecha_inicio_prevista
 * @property \Carbon\Carbon|null $fecha_fin_prevista
 * @property \Carbon\Carbon|null $fecha_fin_real
 * @property int $orden
 */
class PlanActuacionCiudadano extends Model
{
    protected $table = 'plan_actuaciones_ciudadano';

    protected $fillable = [
        'plan_id', 'descripcion', 'prestacion_id', 'estado',
        'fecha_inicio_prevista', 'fecha_fin_prevista', 'fecha_fin_real', 'orden',
    ];

    protected $casts = [
        'fecha_inicio_prevista' => 'date',
        'fecha_fin_prevista'    => 'date',
        'fecha_fin_real'        => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Plan al que pertenece este compromiso.
     *
     * @return BelongsTo<PlanDeIntervencion, self>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_id');
    }

    /**
     * Prestación del catálogo relacionada, si aplica.
     *
     * @return BelongsTo<Prestacion, self>
     */
    public function prestacion(): BelongsTo
    {
        return $this->belongsTo(Prestacion::class, 'prestacion_id');
    }
}
