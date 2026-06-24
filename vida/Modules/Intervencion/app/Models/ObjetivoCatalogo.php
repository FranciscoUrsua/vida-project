<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Objetivo del catálogo configurable desde el backoffice para un tipo de plan.
 *
 * Los objetivos tienen dos niveles: generales (propósito amplio, sin área temática)
 * y específicos (resultados concretos vinculados a un área temática = TipoFicha).
 * Ambos niveles son independientes entre sí: los específicos NO son hijos de un general;
 * pertenecen al plan directamente y se activan cuando el diagnóstico incluye fichas
 * del área temática correspondiente.
 *
 * @property int      $id
 * @property int      $tipo_plan_id
 * @property int|null $tipo_ficha_id FK al área temática (solo para específicos)
 * @property string   $nivel 'general' | 'especifico'
 * @property string   $texto
 * @property bool     $activo
 * @property int      $orden
 */
class ObjetivoCatalogo extends Model
{
    protected $table = 'objetivos_catalogo';

    protected $fillable = [
        'tipo_plan_id', 'tipo_ficha_id', 'nivel', 'texto', 'activo', 'orden',
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
