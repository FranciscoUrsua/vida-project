<?php

namespace Modules\Mensajes\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Mensaje individual dentro de un hilo de mensajería interna.
 *
 * Los adjuntos se gestionan a través de spatie/laravel-medialibrary
 * en la colección 'adjuntos_mensaje'.
 *
 * @property int $id
 * @property int $hilo_id
 * @property int $remitente_id
 * @property string $cuerpo
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Mensaje extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'mensajes';

    protected $fillable = [
        'hilo_id',
        'remitente_id',
        'cuerpo',
    ];

    // -------------------------------------------------------------------------
    // Medialibrary
    // -------------------------------------------------------------------------

    /**
     * Registra la colección de adjuntos del mensaje en disco local.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('adjuntos_mensaje')
            ->useDisk('local');
    }

    /**
     * Sin conversiones de imagen para documentos adjuntos.
     *
     * @param \Spatie\MediaLibrary\MediaCollections\Models\Media|null $media
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // Sin conversiones por defecto para documentos adjuntos
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Hilo de conversación al que pertenece el mensaje.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<MensajeHilo, self>
     */
    public function hilo(): BelongsTo
    {
        return $this->belongsTo(MensajeHilo::class, 'hilo_id');
    }

    /**
     * Usuario que envió el mensaje.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, self>
     */
    public function remitente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remitente_id');
    }

    /**
     * Referencias a ciudadanos mencionados en el mensaje.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<MensajeReferenciaCiudadano, self>
     */
    public function referenciasCiudadano(): HasMany
    {
        return $this->hasMany(MensajeReferenciaCiudadano::class, 'mensaje_id');
    }

    /**
     * Registros del mensaje incorporados a Historias Sociales de ciudadanos.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<MensajeRegistroHistoria, self>
     */
    public function registrosHistoria(): HasMany
    {
        return $this->hasMany(MensajeRegistroHistoria::class, 'mensaje_id');
    }
}
