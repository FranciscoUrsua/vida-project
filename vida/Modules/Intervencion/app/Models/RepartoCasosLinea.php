<?php

namespace Modules\Intervencion\Models;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un caso dentro de un reparto por salida, con el destino propuesto.
 *
 * Los casos de una misma unidad de convivencia comparten destino.
 *
 * @property int $id
 * @property int $reparto_id
 * @property int $historia_id
 * @property int|null $unidad_convivencia_id
 * @property bool $con_actividad
 * @property int $profesional_destino_id
 * @property bool $modificada_por_supervisor
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read RepartoCasos $reparto
 * @property-read HistoriaSocial $historia
 * @property-read User $profesionalDestino
 */
class RepartoCasosLinea extends Model
{
    /** @var string */
    protected $table = 'repartos_casos_lineas';

    /** @var list<string> */
    protected $fillable = [
        'reparto_id',
        'historia_id',
        'unidad_convivencia_id',
        'con_actividad',
        'profesional_destino_id',
        'modificada_por_supervisor',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'con_actividad' => 'boolean',
        'modificada_por_supervisor' => 'boolean',
    ];

    /**
     * Reparto al que pertenece.
     *
     * @return BelongsTo<RepartoCasos, $this>
     */
    public function reparto(): BelongsTo
    {
        return $this->belongsTo(RepartoCasos::class, 'reparto_id');
    }

    /**
     * Historia del caso.
     *
     * @return BelongsTo<HistoriaSocial, $this>
     */
    public function historia(): BelongsTo
    {
        return $this->belongsTo(HistoriaSocial::class, 'historia_id')->withoutGlobalScopes();
    }

    /**
     * Profesional de destino.
     *
     * @return BelongsTo<User, $this>
     */
    public function profesionalDestino(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_destino_id');
    }
}
