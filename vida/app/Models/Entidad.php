<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entidad extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'tipo',
        'direccion',
        'telefono',
    ];

    // Relaciones
    public function centros()
    {
        return $this->hasMany(Centro::class);
    }
}
