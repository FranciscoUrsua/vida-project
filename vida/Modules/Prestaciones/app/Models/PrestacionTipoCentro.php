<?php

namespace Modules\Prestaciones\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Centro\Models\TipoCentro;

/**
 * Relación entre una prestación y un tipo de centro que puede ofrecerla.
 *
 * Es el catálogo de prestaciones del tipo de centro (docs/modulo-tipo-centro.md §6.1):
 * qué tipos de centro pueden prestar cada prestación según las fichas del
 * catálogo oficial. Los centros lo leen por ConfiguracionEfectivaCentro::prestaciones().
 *
 * @property int $id
 * @property int $prestacion_id
 * @property int $tipo_centro_id
 */
class PrestacionTipoCentro extends Model
{
    protected $table = 'prestacion_tipo_centro';

    protected $fillable = ['prestacion_id', 'tipo_centro_id'];

    /**
     * Prestación a la que pertenece este tipo de centro.
     *
     * @return BelongsTo<Prestacion, self>
     */
    public function prestacion(): BelongsTo
    {
        return $this->belongsTo(Prestacion::class, 'prestacion_id');
    }

    /**
     * Tipo de centro que ofrece la prestación.
     *
     * @return BelongsTo<TipoCentro, self>
     */
    public function tipoCentro(): BelongsTo
    {
        return $this->belongsTo(TipoCentro::class, 'tipo_centro_id');
    }
}
