<?php

namespace Modules\Intervencion\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Indicador de un objetivo real dentro de un Plan de Intervención.
 *
 * Puede originarse desde el catálogo (indicador_catalogo_id) o ser redactado
 * libremente por el profesional (indicador_catalogo_id = null).
 * Almacena la valoración actual del indicador y su fecha de registro.
 *
 * @property int $id
 * @property int $plan_objetivo_id
 * @property int|null $indicador_catalogo_id
 * @property string $descripcion
 * @property string $tipo_valoracion
 * @property string|null $valoracion_actual
 * @property Carbon|null $fecha_valoracion
 * @property int|null $seguimiento_id
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
     * @return BelongsTo<PlanObjetivo, $this>
     */
    public function planObjetivo(): BelongsTo
    {
        return $this->belongsTo(PlanObjetivo::class, 'plan_objetivo_id');
    }

    /**
     * Indicador del catálogo del que procede, si aplica.
     *
     * @return BelongsTo<IndicadorCatalogo, $this>
     */
    public function indicadorCatalogo(): BelongsTo
    {
        return $this->belongsTo(IndicadorCatalogo::class, 'indicador_catalogo_id');
    }

    /**
     * Seguimiento que originó la última valoración, si aplica.
     *
     * @return BelongsTo<SeguimientoPlan, $this>
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
     */
    public function estaValorado(): bool
    {
        return $this->valoracion_actual !== null;
    }

    /**
     * Registra una nueva valoración verificando que el valor es válido para el tipo.
     *
     * Deja una fila en el historial (`plan_indicador_valoraciones`, fase 3, F3-7) y
     * actualiza la valoración actual; nunca sobrescribe sin dejar rastro.
     *
     * @param string $valor Valor válido para `tipo_valoracion`.
     * @param int|null $seguimientoId Seguimiento en que se valora, si lo hay.
     * @param int|null $profesionalId Quién valora; por defecto, el usuario autenticado.
     * @param string|null $comentario
     *
     * @return void
     *
     * @throws \InvalidArgumentException Si el valor no es válido para el tipo de valoración.
     */
    public function registrarValoracion(string $valor, ?int $seguimientoId = null, ?int $profesionalId = null, ?string $comentario = null): void
    {
        $posibles = array_keys($this->valoresPosibles());
        if (! in_array($valor, $posibles)) {
            throw new \InvalidArgumentException(
                "Valor '{$valor}' no válido para tipo '{$this->tipo_valoracion}'."
            );
        }

        PlanIndicadorValoracion::create([
            'plan_objetivo_indicador_id' => $this->id,
            'seguimiento_id' => $seguimientoId,
            'valoracion' => $valor,
            'comentario' => $comentario,
            'profesional_id' => $profesionalId ?? auth()->id(),
            'fecha' => now()->toDateString(),
        ]);

        $this->update([
            'valoracion_actual' => $valor,
            'fecha_valoracion' => now()->toDateString(),
            'seguimiento_id' => $seguimientoId,
        ]);
    }

    /**
     * Historial de valoraciones, de la más antigua a la más reciente.
     *
     * @return HasMany<PlanIndicadorValoracion, $this>
     */
    public function valoraciones(): HasMany
    {
        return $this->hasMany(PlanIndicadorValoracion::class, 'plan_objetivo_indicador_id')->orderBy('id');
    }
}
