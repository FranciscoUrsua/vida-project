<?php

namespace Modules\Centro\Models;

use App\Traits\Versionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Red de centros con pool de plazas o lista de espera compartida.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $nombre_corto
 * @property string|null $descripcion
 * @property bool $activa
 * @property Carbon $fecha_alta
 * @property Carbon|null $fecha_baja
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
        'activa' => 'boolean',
        'fecha_alta' => 'date',
        'fecha_baja' => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Centros que pertenecen a esta red.
     *
     * @return BelongsToMany<Centro, self>
     */
    public function centros(): BelongsToMany
    {
        return $this->belongsToMany(Centro::class, 'red_centro', 'red_id', 'centro_id');
    }

    // -------------------------------------------------------------------------
    // Métodos de disponibilidad
    // -------------------------------------------------------------------------

    /**
     * Total de plazas con estado 'libre' en todos los centros de la red.
     * Solo cuenta colecciones activas.
     */
    public function plazasLibresTotal(): int
    {
        return (int) DB::table('plazas')
            ->join('espacios', 'espacios.id', '=', 'plazas.espacio_id')
            ->join('colecciones_plazas', 'colecciones_plazas.id', '=', 'espacios.coleccion_plazas_id')
            ->join('red_centro', 'red_centro.centro_id', '=', 'colecciones_plazas.centro_id')
            ->where('red_centro.red_id', $this->id)
            ->where('plazas.estado', 'libre')
            ->where('colecciones_plazas.activa', true)
            ->whereNull('plazas.deleted_at')
            ->count();
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra redes activas.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }
}
