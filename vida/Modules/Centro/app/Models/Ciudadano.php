<?php

namespace Modules\Centro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stub del modelo Ciudadano para uso interno del módulo Centro.
 *
 * La implementación completa (cifrado, motor de matching, unidades de
 * convivencia, fichas) corresponde a Modules\Ciudadania\Models\Ciudadano.
 * Este stub permite que los modelos del módulo Centro resuelvan la relación
 * hasta que ese módulo esté implementado. Cuando se implemente, reemplazar
 * las referencias a este modelo por Modules\Ciudadania\Models\Ciudadano.
 *
 * @see docs/modulo-ciudadania.md
 */
class Ciudadano extends Model
{
    use SoftDeletes;

    protected $table = 'ciudadanos';

    protected $fillable = [
        'alias',
        'nombre',
        'apellido1',
        'apellido2',
        'fecha_nacimiento',
        'sexo',
        'domicilio',
        'latitud',
        'longitud',
        'telefono',
        'email',
        'nivel_identificacion',
        'contexto_alta',
        'activo',
    ];

    protected $casts = [
        'activo'          => 'boolean',
        'fecha_nacimiento' => 'date',
    ];
}
