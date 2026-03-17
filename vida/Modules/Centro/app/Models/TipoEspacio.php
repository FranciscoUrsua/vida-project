<?php

namespace Modules\Centro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoEspacio extends Model
{
    protected $table = 'tipos_espacio';

    protected $fillable = ['nombre', 'descripcion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function espacios(): HasMany
    {
        return $this->hasMany(Espacio::class, 'tipo_espacio_id');
    }
}
