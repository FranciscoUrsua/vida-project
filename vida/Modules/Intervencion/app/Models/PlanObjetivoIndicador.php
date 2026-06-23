<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Indicador de un objetivo real dentro de un Plan de Intervención.
 *
 * Puede originarse desde el catálogo (indicador_catalogo_id) o ser redactado
 * libremente por el profesional (indicador_catalogo_id = null).
 * Almacena la valoración actual del indicador y su fecha de registro.
 *
 * @property int         $id
 * @property int         $plan_objetivo_id
 * @property int|null    $indicador_catalogo_id
 * @property string      $descripcion
 * @property string      $tipo_valoracion
 * @property string|null $valoracion_actual
 * @property \Carbon\Carbon|null $fecha_valoracion
 * @property int|null    $seguimiento_id
 */
class PlanObjetivoIndicador extends Model
{
    protected $table = 'plan_objetivo_indicadores';

    protected $fillable = [
        'plan_objetivo_id',
        'indicador_catalogo_id',
        'descripcion',
        'tipo_valoracion',
        'valoracion_actual',
        'fecha_valoracion',
        'seguimiento_id',
    ];

    protected $casts = [
        'fecha_valoracion' => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Objetivo del plan al que pertenece este indicador.
     *
     * @return BelongsTo<PlanObjetivo, self>
     */
    public function planObjetivo(): BelongsTo
    {
        return $this->belongsTo(PlanObjetivo::class, 'plan_objetivo_id');
    }

    /**
     * Indicador del catálogo del que procede, si aplica.
     *
     * @return BelongsTo<IndicadorCatalogo, self>
     */
    public function indicadorCatalogo(): BelongsTo
    {
        return $this->belongsTo(IndicadorCatalogo::class, 'indicador_catalogo_id');
    }

    /**
     * Seguimiento que originó la última valoración, si aplica.
     *
     * @return BelongsTo<SeguimientoPlan, self>
     */
    public function seguimiento(): BelongsTo
    {
        return $this->belongsTo(SeguimientoPlan::class, 'seguimiento_id');
    }

    // -------------------------------------------------------------------------
    // Métodos de dominio
    // -------------------------------------------------------------------------

    /**
     * Etiquetas de valores posibles para este indicador.
     *
     * @return array<string,string>
     */
    public function valoresPosibles(): array
    {
        return IndicadorCatalogo::etiquetasValoración($this->tipo_valoracion);
    }

    /**
     * Indica si el indicador tiene una valoración registrada.
     *
     * @return bool
     */
    public function estaValorado(): bool
    {
        return $this->valoracion_actual !== null;
    }

    /**
     * Registra una nueva valoración verificando que el valor es válido para el tipo.
     *
     * @param  string   $valor
     * @param  int|null $seguimientoId
     * @return void
     * @throws \InvalidArgumentException Si el valor no es válido para el tipo de valoración.
     */
    public function registrarValoracion(string $valor, ?int $seguimientoId = null): void
    {
        $posibles = array_keys($this->valoresPosibles());
        if (! in_array($valor, $posibles)) {
            throw new \InvalidArgumentException(
                "Valor '{$valor}' no válido para tipo '{$this->tipo_valoracion}'."
            );
        }

        $this->update([
            'valoracion_actual' => $valor,
            'fecha_valoracion'  => now()->toDateString(),
            'seguimiento_id'    => $seguimientoId,
        ]);
    }
}
