<?php

namespace Modules\Organizacion\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Modelo de Zona (subdivisión del distrito).
 *
 * Agrupación configurable de unidades censales dentro de un distrito.
 * Permite distribuir la carga de trabajo entre profesionales del mismo centro.
 *
 * @property int $id
 * @property string $nombre
 * @property int $distrito_id
 * @property string|null $descripcion
 * @property bool $activa
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @see docs/glosario.md § Zona
 */
class Zona extends Model
{
    /** @var string */
    protected $table = 'zonas';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'distrito_id',
        'descripcion',
        'activa',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'activa' => 'boolean',
    ];

    /**
     * Distrito al que pertenece esta zona.
     *
     * @return BelongsTo<Distrito, Zona>
     */
    public function distrito(): BelongsTo
    {
        return $this->belongsTo(Distrito::class, 'distrito_id');
    }

    /**
     * Filtra únicamente las zonas activas.
     *
     * @param Builder<Zona> $consulta
     *
     * @return Builder<Zona>
     */
    public function scopeActivas(Builder $consulta): Builder
    {
        return $consulta->where('activa', true);
    }
}
