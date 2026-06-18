<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Objetivo real de un Plan de Intervención.
 *
 * Puede originarse desde el catálogo (objetivo_catalogo_id) o ser redactado
 * libremente por el profesional (objetivo_catalogo_id = null).
 * Los específicos se vinculan a un general del mismo plan.
 *
 * @property int $id
 * @property int $plan_id
 * @property int|null $objetivo_catalogo_id
 * @property string $nivel  'general' | 'especifico'
 * @property int|null $objetivo_general_id  FK a plan_objetivos
 * @property string $texto
 * @property string $estado  'pendiente' | 'en_proceso' | 'conseguido' | 'abandonado'
 * @property int $orden
 */
class PlanObjetivo extends Model
{
    protected $table = 'plan_objetivos';

    protected $fillable = [
        'plan_id', 'objetivo_catalogo_id', 'nivel', 'objetivo_general_id',
        'texto', 'estado', 'orden',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Plan al que pertenece este objetivo.
     *
     * @return BelongsTo<PlanDeIntervencion, self>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_id');
    }

    /**
     * Objetivo del catálogo del que procede, si aplica.
     *
     * @return BelongsTo<ObjetivoCatalogo, self>
     */
    public function objetivoCatalogo(): BelongsTo
    {
        return $this->belongsTo(ObjetivoCatalogo::class, 'objetivo_catalogo_id');
    }

    /**
     * Objetivo general del que depende este específico.
     *
     * @return BelongsTo<self, self>
     */
    public function objetivoGeneral(): BelongsTo
    {
        return $this->belongsTo(self::class, 'objetivo_general_id');
    }

    /**
     * Objetivos específicos que dependen de este general.
     *
     * @return HasMany<self>
     */
    public function objetivosEspecificos(): HasMany
    {
        return $this->hasMany(self::class, 'objetivo_general_id')->orderBy('orden');
    }
}
