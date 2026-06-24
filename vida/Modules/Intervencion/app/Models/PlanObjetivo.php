<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Objetivo real de un Plan de Intervención.
 *
 * Puede originarse desde el catálogo (objetivo_catalogo_id) o ser redactado
 * libremente por el profesional (objetivo_catalogo_id = null).
 * Los generales y los específicos son independientes entre sí: los específicos
 * pertenecen a un área temática (tipo_ficha_id) y se añaden directamente al plan
 * sin ser hijos de ningún objetivo general.
 *
 * @property int      $id
 * @property int      $plan_id
 * @property int|null $objetivo_catalogo_id
 * @property string   $nivel 'general' | 'especifico'
 * @property int|null $tipo_ficha_id Área temática (solo para específicos)
 * @property string   $texto
 * @property string   $estado 'pendiente' | 'en_proceso' | 'conseguido' | 'abandonado'
 * @property int      $orden
 */
class PlanObjetivo extends Model
{
    protected $table = 'plan_objetivos';

    protected $fillable = [
        'plan_id', 'objetivo_catalogo_id', 'nivel', 'tipo_ficha_id',
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
     * Área temática (tipo de ficha) a la que está vinculado este objetivo específico.
     *
     * @return BelongsTo<TipoFicha, self>
     */
    public function tipoFicha(): BelongsTo
    {
        return $this->belongsTo(TipoFicha::class, 'tipo_ficha_id');
    }

    /**
     * Indicador de valoración de este objetivo en el plan.
     *
     * @return HasOne<PlanObjetivoIndicador>
     */
    public function indicador(): HasOne
    {
        return $this->hasOne(PlanObjetivoIndicador::class, 'plan_objetivo_id');
    }

    // -------------------------------------------------------------------------
    // Métodos de dominio
    // -------------------------------------------------------------------------

    /**
     * Crea el indicador de este objetivo desde el catálogo o con datos ex-novo.
     * Si el objetivo procede del catálogo y tiene indicador, lo hereda.
     * Si es ex-novo, usa la descripción y tipo pasados como parámetro.
     *
     * @param  string|null $descripcionExnovo Descripción para indicadores sin origen en catálogo.
     * @param  string      $tipoValoración    Tipo de escala cuando no hay catálogo.
     * @return PlanObjetivoIndicador
     */
    public function instanciarIndicador(
        ?string $descripcionExnovo = null,
        string $tipoValoración = 'conseguido_proceso_no'
    ): PlanObjetivoIndicador {
        $indicadorCatalogo = $this->objetivoCatalogo?->indicador;

        return PlanObjetivoIndicador::create([
            'plan_objetivo_id'      => $this->id,
            'indicador_catalogo_id' => $indicadorCatalogo?->id,
            'descripcion'           => $indicadorCatalogo?->descripcion ?? $descripcionExnovo ?? '',
            'tipo_valoracion'       => $indicadorCatalogo?->tipo_valoracion ?? $tipoValoración,
            'valoracion_actual'     => null,
        ]);
    }
}
