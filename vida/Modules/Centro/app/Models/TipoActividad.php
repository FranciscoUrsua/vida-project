<?php

namespace Modules\Centro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoActividad extends Model
{
    protected $table = 'tipos_actividad';

    protected $fillable = ['nombre', 'descripcion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function actividades(): HasMany
    {
        return $this->hasMany(Actividad::class, 'tipo_actividad_id');
    }
}
