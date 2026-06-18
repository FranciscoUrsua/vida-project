<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Objetivo del catálogo configurable desde el backoffice para un tipo de plan.
 *
 * Los objetivos tienen dos niveles: generales (propósito amplio) y específicos
 * (resultados concretos). Los específicos se vinculan a un general del mismo tipo.
 *
 * @property int $id
 * @property int $tipo_plan_id
 * @property string $nivel  'general' | 'especifico'
 * @property int|null $objetivo_general_id  FK a sí mismo para específicos
 * @property string $texto
 * @property bool $activo
 * @property int $orden
 */
class ObjetivoCatalogo extends Model
{
    protected $table = 'objetivos_catalogo';

    protected $fillable = [
        'tipo_plan_id', 'nivel', 'objetivo_general_id', 'texto', 'activo', 'orden',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden'  => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Tipo de plan al que pertenece este objetivo.
     *
     * @return BelongsTo<TipoPlan, self>
     */
    public function tipoPlan(): BelongsTo
    {
        return $this->belongsTo(TipoPlan::class, 'tipo_plan_id');
    }

    /**
     * Objetivo general al que pertenece este objetivo específico.
     *
     * @return BelongsTo<self, self>
     */
    public function objetivoGeneral(): BelongsTo
    {
        return $this->belongsTo(self::class, 'objetivo_general_id');
    }

    /**
     * Objetivos específicos activos que dependen de este objetivo general.
     *
     * @return HasMany<self>
     */
    public function objetivosEspecificos(): HasMany
    {
        return $this->hasMany(self::class, 'objetivo_general_id')
            ->where('activo', true)
            ->orderBy('orden');
    }
}
