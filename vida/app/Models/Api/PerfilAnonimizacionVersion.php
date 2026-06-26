<?php

namespace App\Models\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Snapshot histórico de un perfil de anonimización.
 *
 * Cada fila representa el estado de 'campos' y 'k_valor' ANTES de un cambio.
 * El registro en PerfilAnonimizacion tiene siempre el estado actual.
 *
 * Los snapshots son inmutables: no tienen updated_at y no deben modificarse
 * una vez creados. Ver docs/anonimizacion.md § 6.4.
 *
 * @property int $id
 * @property int $perfil_id
 * @property int $version
 * @property array<string, mixed> $campos
 * @property int|null $k_valor
 * @property Carbon $created_at
 */
class PerfilAnonimizacionVersion extends Model
{
    protected $table = 'perfil_anonimizacion_versiones';

    const UPDATED_AT = null;

    protected $fillable = [
        'perfil_id',
        'version',
        'campos',
        'k_valor',
    ];

    protected $casts = [
        'campos' => 'array',
        'version' => 'integer',
        'k_valor' => 'integer',
    ];

    /**
     * @return BelongsTo<PerfilAnonimizacion, $this>
     */
    public function perfil(): BelongsTo
    {
        return $this->belongsTo(PerfilAnonimizacion::class, 'perfil_id');
    }
}
