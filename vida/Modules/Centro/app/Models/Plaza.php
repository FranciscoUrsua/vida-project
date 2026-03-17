<?php

namespace Modules\Centro\Models;

use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Plaza individual dentro de un espacio.
 *
 * Unidad mínima asignable a una persona.
 * El estado se desnormaliza para consultas rápidas; la ocupación efectiva
 * se rastrea a través de la Prescripcion activa que apunta a esta plaza.
 *
 * estado: libre | ocupada | reservada | mantenimiento
 *
 * @property int $id
 * @property int $espacio_id
 * @property string $nombre
 * @property string $estado
 * @property bool $activa
 */
class Plaza extends Model
{
    use SoftDeletes;
    use Versionable;

    protected $table = 'plazas';

    protected $fillable = [
        'espacio_id',
        'nombre',
        'estado',
        'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    public function espacio(): BelongsTo
    {
        return $this->belongsTo(Espacio::class, 'espacio_id');
    }

    /** Prescripción activa en esta plaza. */
    public function prescripcion(): HasOne
    {
        return $this->hasOne(Prescripcion::class, 'plaza_id')
            ->where('estado', 'activa');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeLibres(Builder $query): Builder
    {
        return $query->where('estado', 'libre');
    }

    public function scopeOcupadas(Builder $query): Builder
    {
        return $query->where('estado', 'ocupada');
    }
}
