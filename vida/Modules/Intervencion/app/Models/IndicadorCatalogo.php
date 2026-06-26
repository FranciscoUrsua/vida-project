<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Indicador de medición asociado a un objetivo del catálogo.
 *
 * Cada objetivo del catálogo tiene exactamente un indicador (unique constraint).
 * Define qué se mide y el sistema de valoración usado (3 escalas posibles).
 *
 * @property int $id
 * @property int $objetivo_catalogo_id
 * @property string $descripcion
 * @property string $tipo_valoracion 'conseguido_proceso_no' | 'favorable_mantiene_desfavorable' | 'si_no'
 */
class IndicadorCatalogo extends Model
{
    protected $table = 'indicadores_catalogo';

    protected $fillable = [
        'objetivo_catalogo_id',
        'descripcion',
        'tipo_valoracion',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Objetivo del catálogo al que pertenece este indicador.
     *
     * @return BelongsTo<ObjetivoCatalogo, $this>
     */
    public function objetivoCatalogo(): BelongsTo
    {
        return $this->belongsTo(ObjetivoCatalogo::class, 'objetivo_catalogo_id');
    }

    /**
     * Indicadores instanciados en planes que proceden de este indicador del catálogo.
     *
     * @return HasMany<PlanObjetivoIndicador, $this>
     */
    public function indicadoresPlan(): HasMany
    {
        return $this->hasMany(PlanObjetivoIndicador::class, 'indicador_catalogo_id');
    }

    // -------------------------------------------------------------------------
    // Métodos de dominio
    // -------------------------------------------------------------------------

    /**
     * Valores posibles para la instancia de este indicador.
     *
     * @return array<string>
     */
    public function valoresPosibles(): array
    {
        return array_keys(self::etiquetasValoración($this->tipo_valoracion));
    }

    /**
     * Etiquetas visibles para cada valor según el tipo de valoración.
     *
     * @return array<string,string>
     */
    public static function etiquetasValoración(string $tipo): array
    {
        return match ($tipo) {
            'conseguido_proceso_no' => [
                'conseguido' => 'Conseguido',
                'en_proceso' => 'En proceso',
                'no_conseguido' => 'No conseguido',
            ],
            'favorable_mantiene_desfavorable' => [
                'favorable' => 'Favorable',
                'se_mantiene' => 'Se mantiene',
                'desfavorable' => 'Desfavorable',
            ],
            'si_no' => [
                'si' => 'Sí',
                'no' => 'No',
            ],
            default => [],
        };
    }
}
