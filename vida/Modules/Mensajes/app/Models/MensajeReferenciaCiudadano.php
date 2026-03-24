<?php

namespace Modules\Mensajes\Models;

use App\Models\Ciudadano;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Referencia informativa entre un mensaje y un ciudadano.
 *
 * Permite navegar desde el mensaje al expediente del ciudadano.
 * No implica registro en la Historia Social.
 *
 * @property int $id
 * @property int $mensaje_id
 * @property int $ciudadano_id
 * @property \Illuminate\Support\Carbon $created_at
 */
class MensajeReferenciaCiudadano extends Model
{
    protected $table = 'mensajes_referencias_ciudadano';

    public $timestamps = false;

    protected $fillable = [
        'mensaje_id',
        'ciudadano_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /** @return BelongsTo<Mensaje, MensajeReferenciaCiudadano> */
    public function mensaje(): BelongsTo
    {
        return $this->belongsTo(Mensaje::class, 'mensaje_id');
    }

    /** @return BelongsTo<Ciudadano, MensajeReferenciaCiudadano> */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id');
    }
}
