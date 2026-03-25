<?php

namespace Modules\Centro\Models;

use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Red de centros con pool de plazas o lista de espera compartida.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $nombre_corto
 * @property string|null $descripcion
 * @property bool $activa
 * @property \Illuminate\Support\Carbon $fecha_alta
 * @property \Illuminate\Support\Carbon|null $fecha_baja
 */
class Red extends Model
{
    use SoftDeletes;
    use Versionable;

    protected $table = 'redes';

    protected $fillable = [
        'nombre',
        'nombre_corto',
        'descripcion',
        'activa',
        'fecha_alta',
        'fecha_baja',
    ];

    protected $casts = [
        'activa'     => 'boolean',
        'fecha_alta' => 'date',
        'fecha_baja' => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Centros que pertenecen a esta red.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Modules\Centro\Models\Centro, self>
     */
    public function centros(): BelongsToMany
    {
        return $this->belongsToMany(Centro::class, 'red_centro', 'red_id', 'centro_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra redes activas.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }
}
