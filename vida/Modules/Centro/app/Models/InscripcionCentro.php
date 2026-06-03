<?php

namespace Modules\Centro\Models;

use App\Models\Ciudadano;
use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Inscripción de un ciudadano a un centro.
 *
 * Registro acumulativo: no se eliminan, se cierran con fecha_baja.
 * Requisito para participar en actividades con requiere_inscripcion_centro = true.
 *
 * @property int $id
 * @property int $centro_id
 * @property int $ciudadano_id
 * @property Carbon $fecha_alta
 * @property Carbon|null $fecha_baja
 * @property bool $activa
 */
class InscripcionCentro extends Model
{
    use Versionable;

    protected $table = 'inscripciones_centro';

    protected $fillable = [
        'centro_id',
        'ciudadano_id',
        'fecha_alta',
        'fecha_baja',
        'motivo_baja',
        'activa',
        'notas',
    ];

    protected $casts = [
        'fecha_alta' => 'date',
        'fecha_baja' => 'date',
        'activa' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Centro al que está inscrito el ciudadano.
     *
     * @return BelongsTo<Centro, self>
     */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class, 'centro_id');
    }

    /**
     * Ciudadano inscrito en el centro.
     *
     * @return BelongsTo<Ciudadano, self>
     */
    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra inscripciones activas y sin fecha de baja.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true)->whereNull('fecha_baja');
    }
}
