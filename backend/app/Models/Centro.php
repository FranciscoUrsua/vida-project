<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Centro extends Model
{
    use HasFactory;

    // Opcional: Especifica para claridad
    protected $table = 'centros';

    protected $fillable = [
        'tipo', 'nombre', 'direccion_postal', 'telefono', 'email_contacto', 'director_id', 'campos_especificos'
    ];

    protected $casts = [
        'campos_especificos' => 'array',
        'director_id' => 'integer',
    ];

    public function director()
    {
        return $this->hasOne(Director::class);
    }

    public function profesionales()
    {
        return $this->belongsToMany(Profesional::class)->withTimestamps();
    }

    public function scopeActivos($query)
    {
        return $query->whereHas('director', function ($q) {
            $q->whereNull('fecha_baja');
        });
    }
}
