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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('adjuntos_mensaje')
            ->useDisk('local');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Sin conversiones por defecto para documentos adjuntos
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /** @return BelongsTo<MensajeHilo, Mensaje> */
    public function hilo(): BelongsTo
    {
        return $this->belongsTo(MensajeHilo::class, 'hilo_id');
    }

    /** @return BelongsTo<User, Mensaje> */
    public function remitente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remitente_id');
    }

    /** @return HasMany<MensajeReferenciaCiudadano> */
    public function referenciasCiudadano(): HasMany
    {
        return $this->hasMany(MensajeReferenciaCiudadano::class, 'mensaje_id');
    }

    /** @return HasMany<MensajeRegistroHistoria> */
    public function registrosHistoria(): HasMany
    {
        return $this->hasMany(MensajeRegistroHistoria::class, 'mensaje_id');
    }
}
