<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Director extends Model
{
    use HasFactory, SoftDeletes;

    // Opcional: Especifica
    protected $table = 'directores';

    protected $fillable = ['profesional_id', 'centro_id', 'fecha_alta', 'fecha_baja'];

    protected $casts = [
        'fecha_alta' => 'date',
        'fecha_baja' => 'date',
    ];

    public function profesional()
    {
        return $this->belongsTo(Profesional::class);
    }

    public function centro()
    {
        return $this->belongsTo(Centro::class);
    }

    public function scopeActivos($query)
    {
        return $query->whereNull('fecha_baja');
    }

    public function darDeBaja($fechaBaja)
    {
        $this->update(['fecha_baja' => $fechaBaja]);
        $this->centro->update(['director_id' => null]);
    }
}
