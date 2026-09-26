<?php

namespace Modules\Mensajes\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Mensajes\Enums\EstadoAlerta;

/**
 * Parte de una alerta o aviso que corresponde a una persona: cada destinatario
 * la reconoce o la cierra por su cuenta, y la escalada también es individual.
 *
 * Los destinatarios de una alerta a un colectivo (rol en una UO) se fijan al
 * crearla; quien entra después en la UO no la recibe.
 *
 * @property int $id
 * @property int $alerta_id
 * @property int $usuario_id
 * @property EstadoAlerta $estado
 * @property Carbon|null $atendida_en
 * @property Carbon|null $escalada_en
 * @property int|null $escalada_a_usuario_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Alerta $alerta
 * @property-read User $usuario
 * @property-read User|null $escaladaA
 */
class AlertaDestinatario extends Model
{
    protected $table = 'alerta_destinatarios';

    protected $fillable = [
        'alerta_id',
        'usuario_id',
        'estado',
        'atendida_en',
        'escalada_en',
        'escalada_a_usuario_id',
    ];

    protected $casts = [
        'estado' => EstadoAlerta::class,
        'atendida_en' => 'datetime',
        'escalada_en' => 'datetime',
    ];

    /**
     * Alerta o aviso al que pertenece.
     *
     * @return BelongsTo<Alerta, self>
     */
    public function alerta(): BelongsTo
    {
        return $this->belongsTo(Alerta::class, 'alerta_id');
    }

    /**
     * Persona que debe reconocerla.
     *
     * @return BelongsTo<User, self>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Supervisor al que se escaló por no reconocerla en plazo.
     *
     * @return BelongsTo<User, self>
     */
    public function escaladaA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalada_a_usuario_id');
    }

    /**
     * Filtra los destinatarios que aún no la han reconocido.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', EstadoAlerta::Pendiente);
    }

    /**
     * Filtra las partes escaladas a un supervisor que siguen abiertas.
     * Es la base de la pantalla de control de alertas del supervisor.
     *
     * @param Builder<self> $query
     * @param User $supervisor Supervisor que recibió la escalada.
     * @return Builder<self>
     */
    public function scopeEscaladasA(Builder $query, User $supervisor): Builder
    {
        return $query->where('escalada_a_usuario_id', $supervisor->id)
            ->where('estado', EstadoAlerta::Escalada);
    }
}
