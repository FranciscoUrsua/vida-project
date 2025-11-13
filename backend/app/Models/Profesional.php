<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\ValidatesIdentification;

class Profesional extends Model
{
    use HasFactory, SoftDeletes, ValidatesIdentification;

    // FIX: Especifica el nombre de tabla en plural español
    protected $table = 'profesionales';

    protected $fillable = [
        'nombre', 'apellido1', 'apellido2', 'tipo_id', 'numero_id', 'email', 'telefono', 'titulacion_id'
    ];

    protected $casts = [
        'titulacion_id' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::bootValidatesIdentification(); // Llama explícito si boot custom
    }

    public function titulacion()
    {
        return $this->belongsTo(Titulacion::class);
    }

    public function centros()
    {
        return $this->belongsToMany(Centro::class, 'centro_profesional')
                    ->withTimestamps()  // Incluye created_at/updated_at del pivot para fecha asignación
                    ->withPivot('created_at');  // Opcional: campos extras del pivot
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
