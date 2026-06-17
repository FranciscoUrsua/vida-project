<?php

namespace Modules\Ciudadania\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Relación entre dos ciudadanos.
 *
 * El campo tipo_relacion almacena el slug del catálogo tipos_relacion.
 * La reciprocidad (registro inverso) la gestiona el trait TieneRelacionesReciprocas
 * cuando esté disponible. Por ahora los registros se crean manualmente en pares.
 *
 * @property int         $id
 * @property int         $ciudadano_id
 * @property int         $ciudadano_relacionado_id
 * @property string      $tipo_relacion              Slug del catálogo tipos_relacion
 * @property string      $fecha_inicio
 * @property string|null $fecha_fin
 * @property string|null $observaciones
 */
class CiudadanoRelacion extends Model
{
    protected $table = 'ciudadano_relaciones';

    protected $fillable = [
        'ciudadano_id',
        'ciudadano_relacionado_id',
        'tipo_relacion',
        'fecha_inicio',
        'fecha_fin',
        'observaciones',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    public function ciudadano(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_id');
    }

    public function ciudadanoRelacionado(): BelongsTo
    {
        return $this->belongsTo(Ciudadano::class, 'ciudadano_relacionado_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /** @param Builder<self> $query */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->whereNull('fecha_fin');
    }
}
