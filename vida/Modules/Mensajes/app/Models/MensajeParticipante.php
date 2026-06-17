<?php

namespace Modules\Mensajes\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Mensajes\Enums\RolParticipante;

/**
 * Participante de un hilo de mensajes.
 *
 * @property int $id
 * @property int $hilo_id
 * @property int $usuario_id
 * @property RolParticipante $rol
 * @property Carbon|null $fecha_ultima_lectura
 * @property Carbon|null $archivado_en
 */
class MensajeParticipante extends Model
{
    protected $table = 'mensajes_participantes';

    public $timestamps = false;

    protected $fillable = [
        'hilo_id',
        'usuario_id',
        'rol',
        'fecha_ultima_lectura',
        'archivado_en',
    ];

    protected $casts = [
        'rol' => RolParticipante::class,
        'fecha_ultima_lectura' => 'datetime',
        'archivado_en' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Hilo de mensajes al que pertenece esta participación.
     *
     * @return BelongsTo<MensajeHilo, self>
     */
    public function hilo(): BelongsTo
    {
        return $this->belongsTo(MensajeHilo::class, 'hilo_id');
    }

    /**
     * Usuario participante del hilo.
     *
     * @return BelongsTo<User, self>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    // -------------------------------------------------------------------------
    // Métodos de dominio
    // -------------------------------------------------------------------------

    /**
     * Número de mensajes del hilo que el participante aún no ha leído.
     *
     * @return int
     */
    public function mensajesNoLeidos(): int
    {
        $query = $this->hilo->mensajes();

        if ($this->fecha_ultima_lectura) {
            $query->where('created_at', '>', $this->fecha_ultima_lectura);
        }

        return $query->count();
    }
}
