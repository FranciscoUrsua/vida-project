<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Professional extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre_apellidos',
        'entidad_adscripcion',
        'centro_unidad_id',
        'categoria_profesional',
        'nivel_responsabilidad',
        'perfil_acceso',
    ];

    protected $casts = [
        'perfil_acceso' => 'array',
    ];

    // Relaciones
    public function centro()
    {
        return $this->belongsTo(Centro::class, 'centro_unidad_id');
    }

    public function socialUsers()
    {
        return $this->hasMany(SocialUser::class, 'profesional_referencia_id');
    }

    public function director()
    {
        return $this->hasOne(Director::class, 'professional_id');
    }

    public function appUser()
    {
        return $this->belongsTo(AppUser::class);
    }

    // Scope para nivel de responsabilidad
    public function scopeDirectores($query)
    {
        return $query->where('nivel_responsabilidad', 'director');
    }
}
