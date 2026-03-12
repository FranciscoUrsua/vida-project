<?php

namespace Modules\Organizacion\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo de Distrito municipal.
 *
 * División territorial del municipio. Los 21 distritos de Madrid
 * son los valores iniciales, adaptables a otros municipios.
 * Un ciudadano tiene asignado un centro de servicios sociales
 * según su distrito de residencia.
 *
 * @property int $id
 * @property string $nombre
 * @property string $codigo
 * @property float|null $latitud
 * @property float|null $longitud
 * @property bool $activo
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @see docs/glosario.md § Distrito
 */
class Distrito extends Model
{
    /** @var string */
    protected $table = 'distritos';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'codigo',
        'latitud',
        'longitud',
        'activo',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'latitud'  => 'decimal:7',
        'longitud' => 'decimal:7',
        'activo'   => 'boolean',
    ];

    /**
     * Zonas pertenecientes a este distrito.
     *
     * @return HasMany<Zona>
     */
    public function zonas(): HasMany
    {
        return $this->hasMany(Zona::class, 'distrito_id');
    }

    /**
     * Filtra únicamente los distritos activos.
     *
     * @param Builder<Distrito> $consulta
     * @return Builder<Distrito>
     */
    public function scopeActivos(Builder $consulta): Builder
    {
        return $consulta->where('activo', true);
    }
}
