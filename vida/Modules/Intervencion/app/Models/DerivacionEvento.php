<?php

namespace Modules\Intervencion\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;
use Modules\Intervencion\Enums\EstadoDerivacion;

/**
 * Evento del historial de una derivación (fase 3, B2).
 *
 * Inmutable: solo inserción (principio 4.2). El modelo rechaza actualizar y borrar, y
 * un trigger de PostgreSQL hace lo mismo fuera de Eloquent. Solo lo escribe
 * DerivacionService.
 *
 * @property int $id
 * @property int $derivacion_id
 * @property int|null $actor_id
 * @property EstadoDerivacion|null $estado_antes
 * @property EstadoDerivacion $estado_despues
 * @property string|null $motivo Cifrado.
 * @property Carbon $created_at
 * @property-read Derivacion $derivacion
 * @property-read User|null $actor
 */
class DerivacionEvento extends Model
{
    /** Solo created_at. */
    public const UPDATED_AT = null;

    /** @var string */
    protected $table = 'derivacion_eventos';

    /** @var list<string> */
    protected $fillable = ['derivacion_id', 'actor_id', 'estado_antes', 'estado_despues', 'motivo'];

    /** @var array<string, string> */
    protected $casts = [
        'estado_antes' => EstadoDerivacion::class,
        'estado_despues' => EstadoDerivacion::class,
        'motivo' => 'encrypted',
        'created_at' => 'datetime',
    ];

    /**
     * Impide modificar o borrar el historial desde Eloquent.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('El historial de la derivación es inmutable: no se modifica.'));
        static::deleting(fn () => throw new LogicException('El historial de la derivación es inmutable: no se borra.'));
    }

    /**
     * Derivación del evento.
     *
     * @return BelongsTo<Derivacion, $this>
     */
    public function derivacion(): BelongsTo
    {
        return $this->belongsTo(Derivacion::class, 'derivacion_id');
    }

    /**
     * Usuario que hizo el cambio.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
