<?php

namespace Modules\Intervencion\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Centro\Models\Centro;
use Modules\Intervencion\Enums\EstadoRepartoCasos;

/**
 * Reparto de los casos de un profesional entre el resto del equipo del centro.
 *
 * Lo propone el sistema y lo confirma el supervisor; hasta la confirmación no
 * cambia ninguna asignación (RN-08). Solo RepartoCasosService escribe aquí.
 *
 * @property int $id
 * @property int $centro_id
 * @property int $profesional_origen_id
 * @property int $iniciado_por_id
 * @property EstadoRepartoCasos $estado
 * @property string $motivo Cifrado.
 * @property Carbon|null $confirmado_en
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Centro $centro
 * @property-read User $profesionalOrigen
 * @property-read User $iniciadoPor
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RepartoCasosLinea> $lineas
 *
 * @see docs/modulo-asignacion.md §5
 */
class RepartoCasos extends Model
{
    /** @var string */
    protected $table = 'repartos_casos';

    /** @var list<string> */
    protected $fillable = [
        'centro_id',
        'profesional_origen_id',
        'iniciado_por_id',
        'estado',
        'motivo',
        'confirmado_en',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'estado' => EstadoRepartoCasos::class,
        'motivo' => 'encrypted',
        'confirmado_en' => 'datetime',
    ];

    /**
     * Centro del reparto.
     *
     * @return BelongsTo<Centro, $this>
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class, 'centro_id');
    }

    /**
     * Profesional cuyos casos se reparten. Incluye los dados de baja: la salida
     * del profesional es justo el motivo habitual del reparto.
     *
     * @return BelongsTo<User, $this>
     */
    public function profesionalOrigen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_origen_id')->withTrashed();
    }

    /**
     * Supervisor que inició el reparto.
     *
     * @return BelongsTo<User, $this>
     */
    public function iniciadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'iniciado_por_id');
    }

    /**
     * Casos del reparto con su destino.
     *
     * @return HasMany<RepartoCasosLinea, $this>
     */
    public function lineas(): HasMany
    {
        return $this->hasMany(RepartoCasosLinea::class, 'reparto_id');
    }
}
