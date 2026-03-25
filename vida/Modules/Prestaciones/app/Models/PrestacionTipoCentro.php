<?php

namespace Modules\Prestaciones\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Relación entre una prestación y un tipo de centro que puede ofrecerla.
 *
 * `tipo_centro` es una clave de catalogos_sistema (grupo: 'centro.tipo'),
 * no una FK a la tabla centros. Registra qué TIPOS de centro pueden prestar
 * cada prestación según las fichas del catálogo oficial.
 */
class PrestacionTipoCentro extends Model
{
    protected $table = 'prestacion_tipo_centro';

    protected $fillable = ['prestacion_id', 'tipo_centro'];

    /**
     * Prestación a la que pertenece este tipo de centro.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Prestacion, self>
     */
    public function prestacion(): BelongsTo
    {
        return $this->belongsTo(Prestacion::class, 'prestacion_id');
    }
}
