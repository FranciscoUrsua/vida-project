<?php

namespace Modules\Centro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Stub del catálogo de prestaciones.
 * La implementación completa corresponde al módulo de Prestaciones.
 */
class Prestacion extends Model
{
    protected $table = 'prestaciones';

    protected $fillable = ['nombre', 'codigo', 'descripcion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function centros(): BelongsToMany
    {
        return $this->belongsToMany(Centro::class, 'centro_prestacion', 'prestacion_id', 'centro_id');
    }
}
