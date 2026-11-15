<?php

namespace Modules\Prestaciones\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;
use Modules\Prestaciones\Enums\AccionEventoSolicitud;
use Modules\Prestaciones\Enums\EstadoSolicitudPrestacion;
use Modules\Prestaciones\Enums\OrigenActualizacion;

/**
 * Evento del historial de una solicitud de prestación (RN-11).
 *
 * Inmutable: solo inserción (principio 4.2). El modelo rechaza actualizar y borrar, y un
 * trigger de PostgreSQL hace lo mismo. No se purga con la retención de la auditoría.
 *
 * @property int $id
 * @property int $solicitud_id
 * @property AccionEventoSolicitud $accion
 * @property EstadoSolicitudPrestacion|null $estado_antes
 * @property EstadoSolicitudPrestacion|null $estado_despues
 * @property OrigenActualizacion $origen
 * @property int|null $usuario_id
 * @property array<string, mixed>|null $detalle
 * @property string|null $nota Cifrada.
 * @property Carbon $created_at
 * @property-read User|null $usuario
 */
class SolicitudPrestacionEvento extends Model
{
    /** Solo created_at. */
    public const UPDATED_AT = null;

    /** @var string */
    protected $table = 'solicitud_prestacion_eventos';

    /** @var list<string> */
    protected $fillable = ['solicitud_id', 'accion', 'estado_antes', 'estado_despues', 'origen', 'usuario_id', 'detalle', 'nota'];

    /** @var array<string, string> */
    protected $casts = [
        'accion' => AccionEventoSolicitud::class,
        'estado_antes' => EstadoSolicitudPrestacion::class,
        'estado_despues' => EstadoSolicitudPrestacion::class,
        'origen' => OrigenActualizacion::class,
        'detalle' => 'array',
        'nota' => 'encrypted',
        'created_at' => 'datetime',
    ];

    /**
     * Impide modificar o borrar el historial desde Eloquent.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('El historial de la solicitud es inmutable.'));
        static::deleting(fn () => throw new LogicException('El historial de la solicitud es inmutable.'));
    }

    /**
     * Usuario que actuó, si lo hubo.
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id')->withTrashed();
    }
}
