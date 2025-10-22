<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Centro extends Model
{
    use HasFactory;

    protected $fillable = [
        'entidad_id',
        'nombre',
        'tipo_servicio',
        'capacidad',
        'direccion',
    ];

    // Relaciones
    public function entidad()
    {
        return $this->belongsTo(Entidad::class);
    }

    public function servicios()
    {
        return $this->hasMany(Servicio::class);
    }

    public function socialUsers()
    {
        return $this->hasMany(SocialUser::class, 'centro_adscripcion_id');
    }

    public function professionals()
    {
        return $this->hasMany(Professional::class, 'centro_unidad_id');
    }

    public function directors()
    {
        return $this->hasMany(Director::class, 'centro_id');
    }
}
