<?php

namespace Modules\Centro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SegmentoPoblacion extends Model
{
    protected $table = 'segmentos_poblacion';

    protected $fillable = ['nombre', 'descripcion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function centros(): BelongsToMany
    {
        return $this->belongsToMany(Centro::class, 'centro_segmento_poblacion', 'segmento_poblacion_id', 'centro_id');
    }
}
