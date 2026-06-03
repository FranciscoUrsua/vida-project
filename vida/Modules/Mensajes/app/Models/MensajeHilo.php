<?php

namespace Modules\Mensajes\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Hilo de conversación entre dos profesionales.
 *
 * @property int $id
 * @property string $asunto
 * @property int $creado_por_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MensajeHilo extends Model
{
    protected $table = 'mensajes_hilos';

    protected $fillable = [
        'asunto',
        'creado_por_id',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Participantes del hilo de conversación.
     *
     * @return HasMany<MensajeParticipante, self>
     */
    public function participantes(): HasMany
    {
        return $this->hasMany(MensajeParticipante::class, 'hilo_id');
    }

    /**
     * Mensajes del hilo ordenados cronológicamente.
     *
     * @return HasMany<Mensaje, self>
     */
    public function mensajes(): HasMany
    {
        return $this->hasMany(Mensaje::class, 'hilo_id')->orderBy('created_at');
    }

    /**
     * Usuario que creó el hilo de conversación.
     *
     * @return BelongsTo<User, self>
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    /** @return HasOne<Mensaje> */
    public function ultimoMensaje(): HasOne
    {
        return $this->hasOne(Mensaje::class, 'hilo_id')->latestOfMany();
    }

    // -------------------------------------------------------------------------
    // Métodos de dominio
    // -------------------------------------------------------------------------

    /**
     * Comprueba si un usuario es participante activo del hilo.
     */
    public function tieneParticipante(int $usuarioId): bool
    {
        return $this->participantes()->where('usuario_id', $usuarioId)->exists();
    }
}
