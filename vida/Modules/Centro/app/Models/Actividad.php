<?php

namespace Modules\Centro\Models;

use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Actividad programada en un centro.
 *
 * modo_acceso: libre | prescripcion | mixta
 *   mixta = hay cupo para prescripciones y cupo libre simultáneamente.
 *
 * @property int $id
 * @property int $centro_id
 * @property int $tipo_actividad_id
 * @property string $nombre
 * @property string|null $descripcion
 * @property string $modo_acceso
 * @property int|null $aforo_total
 * @property int|null $aforo_prescripcion
 * @property bool $requiere_inscripcion_centro
 * @property bool $activa
 * @property \Illuminate\Support\Carbon $fecha_alta
 * @property \Illuminate\Support\Carbon|null $fecha_baja
 */
class Actividad extends Model
{
    use SoftDeletes;
    use Versionable;

    protected $table = 'actividades';

    protected $fillable = [
        'centro_id',
        'tipo_actividad_id',
        'nombre',
        'descripcion',
        'modo_acceso',
        'aforo_total',
        'aforo_prescripcion',
        'requiere_inscripcion_centro',
        'activa',
        'fecha_alta',
        'fecha_baja',
        'notas',
    ];

    protected $casts = [
        'aforo_total'                 => 'integer',
        'aforo_prescripcion'          => 'integer',
        'requiere_inscripcion_centro' => 'boolean',
        'activa'                      => 'boolean',
        'fecha_alta'                  => 'date',
        'fecha_baja'                  => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class, 'centro_id');
    }

    public function tipoActividad(): BelongsTo
    {
        return $this->belongsTo(TipoActividad::class, 'tipo_actividad_id');
    }

    public function sesiones(): HasMany
    {
        return $this->hasMany(SesionActividad::class, 'actividad_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true)->whereNull('fecha_baja');
    }
}
