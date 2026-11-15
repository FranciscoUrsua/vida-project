<?php

namespace Modules\Prestaciones\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Prestaciones\Enums\OrigenActualizacion;
use Modules\Prestaciones\Enums\SentidoResolucion;

/**
 * Resolución de una solicitud (§2.5 del diseño): fecha, sentido y una línea por prestación
 * resuelta, que puede no coincidir con la solicitada. La escribe ResolucionService.
 *
 * @property int $id
 * @property int $solicitud_id
 * @property Carbon $fecha
 * @property SentidoResolucion $sentido
 * @property OrigenActualizacion $origen
 * @property int|null $registrada_por_id
 */
class ResolucionSolicitud extends Model
{
    /** @var string */
    protected $table = 'resoluciones_solicitud';

    /** @var list<string> */
    protected $fillable = ['solicitud_id', 'fecha', 'sentido', 'origen', 'registrada_por_id'];

    /** @var array<string, string> */
    protected $casts = ['fecha' => 'date', 'sentido' => SentidoResolucion::class, 'origen' => OrigenActualizacion::class];

    /**
     * Solicitud resuelta.
     *
     * @return BelongsTo<SolicitudPrestacion, $this>
     */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudPrestacion::class, 'solicitud_id');
    }

    /**
     * Líneas de la resolución.
     *
     * @return HasMany<ResolucionLinea, $this>
     */
    public function lineas(): HasMany
    {
        return $this->hasMany(ResolucionLinea::class, 'resolucion_id')->orderBy('id');
    }
}
