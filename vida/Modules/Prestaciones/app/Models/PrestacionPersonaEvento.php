<?php

namespace Modules\Prestaciones\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;
use Modules\Prestaciones\Enums\EstadoPrestacionPersona;

/**
 * Cambio de estado de una prestación de la persona. Inmutable (principio 4.2): el modelo y un
 * trigger rechazan actualizar y borrar.
 *
 * @property int $id
 * @property int $prestacion_persona_id
 * @property int|null $usuario_id
 * @property EstadoPrestacionPersona|null $estado_antes
 * @property EstadoPrestacionPersona $estado_despues
 * @property string|null $motivo Cifrado.
 * @property Carbon $created_at
 */
class PrestacionPersonaEvento extends Model
{
    /** Solo created_at. */
    public const UPDATED_AT = null;

    /** @var string */
    protected $table = 'prestacion_persona_eventos';

    /** @var list<string> */
    protected $fillable = ['prestacion_persona_id', 'usuario_id', 'estado_antes', 'estado_despues', 'motivo'];

    /** @var array<string, string> */
    protected $casts = [
        'estado_antes' => EstadoPrestacionPersona::class,
        'estado_despues' => EstadoPrestacionPersona::class,
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
        static::updating(fn () => throw new LogicException('El historial de la prestación es inmutable.'));
        static::deleting(fn () => throw new LogicException('El historial de la prestación es inmutable.'));
    }

    /**
     * Usuario que hizo el cambio.
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id')->withTrashed();
    }
}
