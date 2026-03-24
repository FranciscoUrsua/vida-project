<?php

namespace Modules\Mensajes\Models;

use App\Models\Ciudadano;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Mensajes\Enums\VisibilidadMensaje;

/**
 * Registro de un mensaje en la Historia Social de un ciudadano.
 *
 * Materializa la decisión explícita del TSR de incorporar un mensaje
 * (o una versión editada) al expediente del ciudadano. Solo el TSR
 * responsable del expediente puede crear estos registros.
 *
 * @property int $id
 * @property int $mensaje_id
 * @property int $ciudadano_id
 * @property int $registrado_por_id
 * @property string $cuerpo_registrado    Puede diferir del mensaje original
 * @property VisibilidadMensaje $visibilidad
 * @property \Illuminate\Support\Carbon $registrado_en
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class MensajeRegistroHistoria extends Model
{
    protected $table = 'mensajes_registro_historia';

    protected $fillable = [
        'mensaje_id',
        'ciudadano_id',
        'registrado_por_id',
        'cuerpo_registrado',
        'visibilidad',
        'registrado_en',
    ];

    protected $casts = [
        'visibilidad'   => VisibilidadMensaje::class,
        'registrado_en' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /** @return BelongsTo<Mensaje, MensajeRegistroHistoria> */
    public function mensaje(): BelongsTo
    {
        return $this->belongsTo(Mensaje::class, 'mensaje_id');
    }

    /** @return BelongsTo<Ciudadano, MensajeRegistroHistoria> */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id');
    }

    /** @return BelongsTo<User, MensajeRegistroHistoria> */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }
}
