<?php

namespace Modules\Centro\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de tipos de actividad ofrecidos en los centros.
 *
 * Permite clasificar y filtrar las actividades (talleres, grupos de apoyo, etc.).
 * El slug permite referencias estables desde código sin depender del id numérico.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $slug  Identificador estable único, ej: 'taller', 'grupo-apoyo'
 * @property string|null $descripcion
 * @property bool $activo
 */
class TipoActividad extends Model
{
    protected $table = 'tipos_actividad';

    protected $fillable = ['nombre', 'slug', 'descripcion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    /**
     * Filtra solo los tipos activos. Usar en selects de backoffice y formularios.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

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
