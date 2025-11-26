<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Servicio extends Model
{
    use HasFactory;

    protected $fillable = [
        'centro_id',
        'nombre',
        'descripcion',
        'categoria',
    ];

    // Relaciones
    public function centro()
    {
        return $this->belongsTo(Centro::class);
    }

    // N:N con prestaciones (agregar pivot después)
    public function prestaciones()
    {
        return $this->belongsToMany(Prestacion::class);
    }
}
