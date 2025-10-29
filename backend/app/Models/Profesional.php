<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Profesional extends Model
{
    use HasFactory, SoftDeletes;

    // FIX: Especifica el nombre de tabla en plural español
    protected $table = 'profesionales';

    protected $fillable = [
        'nombre', 'apellido1', 'apellido2', 'tipo_id', 'numero_id', 'email', 'telefono', 'titulacion_id'
    ];

    protected $casts = [
        'titulacion_id' => 'integer',
    ];

    public function titulacion()
    {
        return $this->belongsTo(Titulacion::class);
    }

    public function centros()
    {
        return $this->belongsToMany(Centro::class)->withTimestamps();
    }

    public function directores()
    {
        return $this->hasMany(Director::class);
    }

    public function asignarComoDirector(Centro $centro, $fechaAlta)
    {
        return Director::create([
            'profesional_id' => $this->id,
            'centro_id' => $centro->id,
            'fecha_alta' => $fechaAlta,
        ]);
    }
}
