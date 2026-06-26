<?php

namespace Modules\Centro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Usuarios\Models\Profesional;

/**
 * Registro de auditoría de un cambio de posición en la lista de espera.
 *
 * Se crea cada vez que un profesional reordena manualmente una prescripción
 * en la lista. Permite trazabilidad de quién cambió qué posición y cuándo.
 *
 * @property int $id
 * @property int $lista_espera_id
 * @property int $posicion_anterior
 * @property int $posicion_nueva
 * @property int $profesional_id
 */
class ListaEsperaMovimiento extends Model
{
    protected $table = 'lista_espera_movimientos';

    protected $fillable = [
        'lista_espera_id',
        'posicion_anterior',
        'posicion_nueva',
        'profesional_id',
    ];

    protected $casts = [
        'posicion_anterior' => 'integer',
        'posicion_nueva' => 'integer',
    ];

    /**
     * Registro de lista de espera al que pertenece este movimiento.
     *
     * @return BelongsTo<ListaEspera, $this>
     */
    public function listaEspera(): BelongsTo
    {
        return $this->belongsTo(ListaEspera::class, 'lista_espera_id');
    }

    /**
     * Profesional que realizó el reordenamiento.
     *
     * @return BelongsTo<Profesional, $this>
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }
}
