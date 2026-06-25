<?php

namespace Modules\Centro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de tipos de espacio físico de un centro.
 *
 * Clasifica los espacios según su naturaleza (habitación individual,
 * habitación doble, sala común, módulo, etc.).
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $slug  Identificador estable único, ej: 'dormitorio-individual', 'sala-comun'
 * @property string|null $descripcion
 * @property bool $activo
 */
class TipoEspacio extends Model
{
    protected $table = 'tipos_espacio';

    protected $fillable = ['nombre', 'slug', 'descripcion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    /**
     * Espacios físicos clasificados con este tipo.
     *
     * @return HasMany<Espacio, self>
     */
    public function espacios(): HasMany
    {
        return $this->hasMany(Espacio::class, 'tipo_espacio_id');
    }
}
