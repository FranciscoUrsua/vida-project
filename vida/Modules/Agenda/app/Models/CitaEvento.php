<?php

namespace Modules\Agenda\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;
use Modules\Agenda\Enums\AccionCitaEvento;
use Modules\Agenda\Enums\ActorTipoCitaEvento;
use Modules\Agenda\Enums\CanalCitaEvento;
use Modules\Agenda\Enums\PedidoPor;

/**
 * Evento del historial de una solicitud o una cita (docs/modulo-citas.md §2.4).
 *
 * Inmutable: solo inserción. El modelo rechaza actualizar y borrar, y un
 * trigger de PostgreSQL hace lo mismo fuera de Eloquent. Forma parte del
 * expediente de atención de la persona: no es auditoría técnica y no se purga.
 * Solo lo escribe RegistroEventosCita.
 *
 * @property int $id
 * @property int|null $solicitud_cita_id
 * @property int|null $cita_id
 * @property AccionCitaEvento $accion
 * @property int|null $actor_id
 * @property ActorTipoCitaEvento $actor_tipo
 * @property CanalCitaEvento|null $canal
 * @property PedidoPor|null $pedido_por
 * @property string|null $estado_antes
 * @property string|null $estado_despues
 * @property string|null $motivo Cifrado.
 * @property array<string, mixed>|null $datos
 * @property Carbon $created_at
 * @property-read Cita|null $cita
 * @property-read SolicitudCita|null $solicitud
 * @property-read User|null $actor
 */
class CitaEvento extends Model
{
    /** Solo created_at. */
    public const UPDATED_AT = null;

    /** @var string */
    protected $table = 'cita_eventos';

    /** @var list<string> */
    protected $fillable = [
        'solicitud_cita_id',
        'cita_id',
        'accion',
        'actor_id',
        'actor_tipo',
        'canal',
        'pedido_por',
        'estado_antes',
        'estado_despues',
        'motivo',
        'datos',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'accion' => AccionCitaEvento::class,
        'actor_tipo' => ActorTipoCitaEvento::class,
        'canal' => CanalCitaEvento::class,
        'pedido_por' => PedidoPor::class,
        'motivo' => 'encrypted',
        'datos' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Rechaza cualquier modificación o borrado.
     *
     * @return void
     *
     * @throws LogicException
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('El historial de la cita es inmutable: no se modifica.'));
        static::deleting(fn () => throw new LogicException('El historial de la cita es inmutable: no se borra.'));
    }

    /**
     * Cita del evento.
     *
     * @return BelongsTo<Cita, $this>
     */
    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id')->withTrashed();
    }

    /**
     * Solicitud del evento.
     *
     * @return BelongsTo<SolicitudCita, $this>
     */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCita::class, 'solicitud_cita_id')->withTrashed();
    }

    /**
     * Usuario que hizo la acción (null si fue el canal externo o el sistema).
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Ciudadano para auditoría.
     *
     * @return int|null
     */
    public function getCiudadanoId(): ?int
    {
        return $this->cita?->ciudadano_id ?? $this->solicitud?->ciudadano_id;
    }
}
