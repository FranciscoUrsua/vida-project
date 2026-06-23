<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Objetivo del catálogo configurable desde el backoffice para un tipo de plan.
 *
 * Los objetivos tienen dos niveles: generales (propósito amplio) y específicos
 * (resultados concretos). Los específicos se vinculan a un general del mismo tipo
 * y pueden tener un área temática (tipo_ficha_id) que determina qué fichas
 * activan la propuesta automática de este objetivo en el plan.
 *
 * @property int      $id
 * @property int      $tipo_plan_id
 * @property int|null $tipo_ficha_id FK al área temática (solo para específicos)
 * @property string   $nivel 'general' | 'especifico'
 * @property int|null $objetivo_general_id FK a sí mismo para específicos
 * @property string   $texto
 * @property bool     $activo
 * @property int      $orden
 */
class ObjetivoCatalogo extends Model
{
    protected $table = 'objetivos_catalogo';

    protected $fillable = [
        'tipo_plan_id', 'tipo_ficha_id', 'nivel', 'objetivo_general_id', 'texto', 'activo', 'orden',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
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

    /**
     * Área temática (tipo de ficha) a la que está vinculado este objetivo específico.
     *
     * @return BelongsTo<TipoFicha, self>
     */
    public function tipoFicha(): BelongsTo
    {
        return $this->belongsTo(TipoFicha::class, 'tipo_ficha_id');
    }

    /**
     * Indicador de medición asociado a este objetivo del catálogo (uno por objetivo).
     *
     * @return HasOne<IndicadorCatalogo>
     */
    public function indicador(): HasOne
    {
        return $this->hasOne(IndicadorCatalogo::class, 'objetivo_catalogo_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra objetivos vinculados a un área temática concreta.
     *
     * @param  Builder<self> $query
     * @param  int           $tipoFichaId
     * @return Builder<self>
     */
    public function scopeDeArea(Builder $query, int $tipoFichaId): Builder
    {
        return $query->where('tipo_ficha_id', $tipoFichaId);
    }
}
