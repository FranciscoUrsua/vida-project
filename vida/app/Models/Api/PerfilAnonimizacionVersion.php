<?php

namespace App\Models\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot histórico de un perfil de anonimización.
 *
 * Cada fila representa el estado de 'campos' y 'k_valor' ANTES de un cambio.
 * El registro en PerfilAnonimizacion tiene siempre el estado actual.
 *
 * Los snapshots son inmutables: no tienen updated_at y no deben modificarse
 * una vez creados. Ver docs/anonimizacion.md § 6.4.
 *
 * @property int      $id
 * @property int      $perfil_id
 * @property int      $version   Versión que tenía el perfil antes del cambio
 * @property array    $campos
 * @property int|null $k_valor
 * @property \Illuminate\Support\Carbon $created_at
 */
class PerfilAnonimizacionVersion extends Model
{
    /** @var string */
    protected $table = 'perfil_anonimizacion_versiones';

    /** Los snapshots son inmutables — no hay updated_at */
    const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'perfil_id',
        'version',
        'campos',
        'k_valor',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'campos'  => 'array',
        'version' => 'integer',
        'k_valor' => 'integer',
    ];

    /**
     * Perfil al que pertenece este snapshot.
     *
     * @return BelongsTo<PerfilAnonimizacion, PerfilAnonimizacionVersion>
     */
    public function perfil(): BelongsTo
    {
        return $this->belongsTo(PerfilAnonimizacion::class, 'perfil_id');
    }
}
