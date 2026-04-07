<?php

namespace Modules\Agenda\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\Espacio;

/**
 * Evento en la agenda del centro.
 *
 * Bloqueo de tiempo sin ciudadano asociado (reuniones, formaciones, mesas
 * de coordinación). No genera historia social. Puede reservar un espacio
 * físico del centro y convocar a profesionales vía pivot evento_usuario.
 *
 * El tipo_evento referencia un valor de catalogos_sistema (grupo: tipo_evento_agenda).
 * No se modela como enum porque es puramente clasificatorio (Principio 3.10).
 *
 * @property int $id
 * @property int $centro_id
 * @property string $tipo_evento
 * @property string $titulo
 * @property string|null $descripcion
 * @property \Illuminate\Support\Carbon $fecha
 * @property string $hora_inicio
 * @property string $hora_fin
 * @property int|null $espacio_id
 * @property int $creado_por_id
 * @property string|null $notas
 */
class EventoAgenda extends Model
{
    use SoftDeletes;

    protected $table = 'eventos_agenda';

    protected $guarded = [];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    public function espacio(): BelongsTo
    {
        return $this->belongsTo(Espacio::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    public function profesionales(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'evento_usuario', 'evento_agenda_id', 'usuario_id')
            ->withPivot(['confirmado', 'notas'])
            ->withTimestamps();
    }

    public function scopeDelDia(Builder $query, $fecha): Builder
    {
        return $query->where('fecha', $fecha);
    }

    public function scopeDelCentro(Builder $query, int $centroId): Builder
    {
        return $query->where('centro_id', $centroId);
    }

    public function scopeDelProfesional(Builder $query, int $usuarioId): Builder
    {
        return $query->whereHas('profesionales', fn (Builder $q) => $q->where('users.id', $usuarioId));
    }
}
