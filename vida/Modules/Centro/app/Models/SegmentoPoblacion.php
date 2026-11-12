<?php

namespace Modules\Centro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Segmento de población al que puede orientarse un centro.
 *
 * Permite clasificar los centros según el perfil de los beneficiarios
 * a los que atienden (mayores, menores, personas sin hogar, etc.).
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $slug  Identificador estable único, ej: 'personas-sin-hogar', 'personas-mayores'
 * @property string|null $descripcion
 * @property bool $activo
 */
class SegmentoPoblacion extends Model
{
    protected $table = 'segmentos_poblacion';

    protected $fillable = ['nombre', 'slug', 'descripcion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    /**
     * Tipos de centro que atienden a este segmento de población (el público es del tipo).
     *
     * @return BelongsToMany<TipoCentro, self>
     */
    public function tiposCentro(): BelongsToMany
    {
        return $this->belongsToMany(TipoCentro::class, 'tipo_centro_segmento_poblacion', 'segmento_poblacion_id', 'tipo_centro_id');
    }
}
