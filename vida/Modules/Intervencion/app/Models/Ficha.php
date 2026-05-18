<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Intervencion\Database\Factories\FichaFactory;

/**
 * Ficha de datos reales de una valoración.
 *
 * Cada ficha corresponde a un TipoFicha y almacena los valores
 * introducidos por el profesional. El campo datos es un JSON libre;
 * el campo notas permite texto sin estructura durante la entrevista.
 *
 * @property int $id
 * @property int $valoracion_id
 * @property int $tipo_ficha_id
 * @property array|null $datos
 * @property string|null $notas
 * @property bool $completada
 */
class Ficha extends Model
{
    use HasFactory;

    protected static function newFactory(): FichaFactory
    {
        return FichaFactory::new();
    }

    protected $table = 'fichas';

    protected $fillable = [
        'valoracion_id',
        'tipo_ficha_id',
        'datos',
        'notas',
        'completada',
    ];

    protected $casts = [
        'datos'      => 'array',
        'completada' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * @return BelongsTo<Valoracion, Ficha>
     */
    public function valoracion(): BelongsTo
    {
        return $this->belongsTo(Valoracion::class, 'valoracion_id');
    }

    /**
     * @return BelongsTo<TipoFicha, Ficha>
     */
    public function tipoFicha(): BelongsTo
    {
        return $this->belongsTo(TipoFicha::class, 'tipo_ficha_id');
    }
}
