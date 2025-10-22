<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prestacion extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'categoria',
        'requisitos',
        'duracion_meses',
        'costo',
    ];

    protected $casts = [
        'requisitos' => 'array',
        'duracion_meses' => 'integer',
        'costo' => 'decimal:2',
    ];

    // Relaciones
    public function servicios()
    {
        return $this->belongsToMany(Servicio::class);
    }

    public function socialUsers()
    {
        return $this->belongsToMany(SocialUser::class);  # Pivot para asignaciones
    }
}
