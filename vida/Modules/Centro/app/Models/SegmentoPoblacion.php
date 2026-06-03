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
 * @property string|null $descripcion
 * @property bool $activo
 */
class SegmentoPoblacion extends Model
{
    protected $table = 'segmentos_poblacion';

    protected $fillable = ['nombre', 'descripcion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    /**
     * Centros que atienden a este segmento de población.
     *
     * @return BelongsToMany<Centro, self>
     */
    public function centros(): BelongsToMany
    {
        return $this->belongsToMany(Centro::class, 'centro_segmento_poblacion', 'segmento_poblacion_id', 'centro_id');
    }
}
