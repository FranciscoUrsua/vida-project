<?php

namespace Modules\Centro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de tipos de actividad ofrecidos en los centros.
 *
 * Permite clasificar y filtrar las actividades (talleres, grupos de apoyo, etc.).
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property bool $activo
 */
class TipoActividad extends Model
{
    protected $table = 'tipos_actividad';

    protected $fillable = ['nombre', 'descripcion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    /**
     * Actividades que pertenecen a este tipo.
     *
     * @return HasMany<Actividad, self>
     */
    public function actividades(): HasMany
    {
        return $this->hasMany(Actividad::class, 'tipo_actividad_id');
    }
}
