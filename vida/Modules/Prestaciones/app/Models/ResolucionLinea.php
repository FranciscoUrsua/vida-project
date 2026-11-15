<?php

namespace Modules\Prestaciones\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Línea de una resolución: una prestación concedida (con condiciones) o denegada (con motivo).
 *
 * @property int $id
 * @property int $resolucion_id
 * @property int $prestacion_id
 * @property bool $concedida
 * @property string|null $motivo_denegacion Cifrado.
 * @property string|null $importe
 * @property string|null $intensidad
 * @property Carbon|null $fecha_desde
 * @property Carbon|null $fecha_hasta
 * @property string|null $observaciones Cifradas.
 */
class ResolucionLinea extends Model
{
    /** @var string */
    protected $table = 'resolucion_lineas';

    /** @var list<string> */
    protected $fillable = ['resolucion_id', 'prestacion_id', 'concedida', 'motivo_denegacion', 'importe', 'intensidad', 'fecha_desde', 'fecha_hasta', 'observaciones'];

    /** @var array<string, string> */
    protected $casts = [
        'concedida' => 'boolean',
        'importe' => 'decimal:2',
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date',
        'motivo_denegacion' => 'encrypted',
        'observaciones' => 'encrypted',
    ];

    /**
     * Resolución a la que pertenece.
     *
     * @return BelongsTo<ResolucionSolicitud, $this>
     */
    public function resolucion(): BelongsTo
    {
        return $this->belongsTo(ResolucionSolicitud::class, 'resolucion_id');
    }

    /**
     * Prestación resuelta.
     *
     * @return BelongsTo<Prestacion, $this>
     */
    public function prestacion(): BelongsTo
    {
        return $this->belongsTo(Prestacion::class, 'prestacion_id')->withTrashed();
    }
}
