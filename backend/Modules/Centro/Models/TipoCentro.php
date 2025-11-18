<?php

namespace Modules\Centro\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoCentro extends Model
{
    use HasFactory;

    protected $table = 'tipos_centro';

    protected $fillable = [
        'nombre',
        'descripcion',
        'plazas',
        'numero_plazas',
        'criterio_asignacion_plazas',
        'publico_objetivo',
    ];

    protected $casts = [
        'plazas' => 'boolean',
        'numero_plazas' => 'integer',
    ];

    // Relación: Un tipo tiene muchos centros
    public function centros()
    {
        return $this->hasMany(Centro::class, 'tipo_centro_id');
    }

    // Scope para tipos con plazas
    public function scopeConPlazas($query)
    {
        return $query->where('plazas', true);
    }
}
