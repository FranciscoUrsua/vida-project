<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot entre TipoValoracion y TipoFicha.
 *
 * Define qué fichas componen cada tipo de valoración, en qué orden y cuáles son obligatorias.
 *
 * @property int $id
 * @property int $tipo_valoracion_id
 * @property int $tipo_ficha_id
 * @property int $orden
 * @property bool $obligatoria
 */
class TipoValoracionFicha extends Model
{
    protected $table = 'tipo_valoracion_fichas';

    protected $fillable = [
        'tipo_valoracion_id',
        'tipo_ficha_id',
        'orden',
        'obligatoria',
    ];

    protected $casts = [
        'orden' => 'integer',
        'obligatoria' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * @return BelongsTo<TipoValoracion, TipoValoracionFicha>
     */
    public function tipoValoracion(): BelongsTo
    {
        return $this->belongsTo(TipoValoracion::class, 'tipo_valoracion_id');
    }

    /**
     * @return BelongsTo<TipoFicha, TipoValoracionFicha>
     */
    public function tipoFicha(): BelongsTo
    {
        return $this->belongsTo(TipoFicha::class, 'tipo_ficha_id');
    }
}
