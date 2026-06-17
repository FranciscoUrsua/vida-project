<?php

namespace Modules\Escalas\Models;

use App\Models\HistoriaSocial;
use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Escalas\Database\Factories\PaseEscalaFactory;
use Modules\Escalas\Enums\EstadoPase;

/**
 * Aplicación concreta de un instrumento de escala a un ciudadano.
 *
 * Cada pase es independiente e inmutable una vez en estado completado.
 * Los scores (total y por sección) y la interpretación se calculan al cerrar
 * y se persisten; no se recalculan en lectura. Esto garantiza fidelidad histórica
 * aunque el schema del TipoEscala se modifique posteriormente.
 *
 * @property int $id
 * @property int $tipo_escala_id
 * @property int $historia_id
 * @property int $profesional_id
 * @property Carbon $fecha
 * @property array $respuestas
 * @property int|null $score_total
 * @property array|null $scores_seccion
 * @property string|null $interpretacion_codigo
 * @property string|null $notas
 * @property EstadoPase $estado
 * @property int|null $ficha_id
 * @property int|null $entrevista_id
 */
class PaseEscala extends Model
{
    use Auditable;

    /** @use HasFactory<PaseEscalaFactory> */
    use HasFactory;

    protected $table = 'pases_escala';

    protected $fillable = [
        'tipo_escala_id',
        'historia_id',
        'profesional_id',
        'fecha',
        'respuestas',
        'score_total',
        'scores_seccion',
        'interpretacion_codigo',
        'notas',
        'estado',
        'ficha_id',
        'entrevista_id',
    ];

    protected $casts = [
        'respuestas' => 'array',
        'scores_seccion' => 'array',
        'fecha' => 'date',
        'estado' => EstadoPase::class,
    ];

    // -------------------------------------------------------------------------
    // Ciclo de vida
    // -------------------------------------------------------------------------

    /**
     * Los campos de score son inmutables en un pase completado.
     */
    protected static function booted(): void
    {
        static::updating(function (self $model) {
            // getOriginal aplica el cast en Laravel 12, por eso comparamos contra el enum
            if ($model->getOriginal('estado') === EstadoPase::Completado) {
                foreach (['score_total', 'scores_seccion', 'interpretacion_codigo'] as $campo) {
                    if ($model->isDirty($campo)) {
                        throw new \LogicException(
                            "El campo {$campo} es inmutable en un pase completado."
                        );
                    }
                }
            }
        });
    }

    // -------------------------------------------------------------------------
    // Lógica de cálculo
    // -------------------------------------------------------------------------

    /**
     * Suma los valores de todas las respuestas y calcula los scores por sección.
     * No persiste; llamar a save() después si se desea guardar.
     *
     * @return void
     */
    public function calcularScores(): void
    {
        $total = 0;
        $porSeccion = [];

        foreach ($this->respuestas as $secId => $items) {
            $subtotal = array_sum($items);
            $porSeccion[$secId] = $subtotal;
            $total += $subtotal;
        }

        $this->score_total = $total;
        $this->scores_seccion = $porSeccion;
    }

    /**
     * Busca el rango de interpretación que corresponde al score_total y asigna su código.
     * Si no encuentra ningún rango, deja interpretacion_codigo como null.
     * No persiste.
     *
     * @return void
     */
    public function asignarInterpretacion(): void
    {
        $rangos = $this->tipoEscala->rangos_interpretacion['rangos'] ?? [];

        foreach ($rangos as $rango) {
            if ($this->score_total >= $rango['desde'] && $this->score_total <= $rango['hasta']) {
                $this->interpretacion_codigo = $rango['codigo'];

                return;
            }
        }

        Log::warning('PaseEscala: score_total fuera de rango de interpretación', [
            'pase_id' => $this->id,
            'score_total' => $this->score_total,
        ]);

        $this->interpretacion_codigo = null;
    }

    /**
     * Orquesta el cierre del pase: valida respuestas, calcula scores, persiste.
     *
     * @return void
     *
     * @throws \LogicException Si falta respuesta para algún ítem del schema.
     */
    public function completar(): void
    {
        $schema = $this->tipoEscala->schema;
        $faltantes = [];

        foreach ($schema['secciones'] as $seccion) {
            foreach ($seccion['items'] as $item) {
                $secId = $seccion['id'];
                $itemId = $item['id'];

                if (! isset($this->respuestas[$secId][$itemId])) {
                    $faltantes[] = "{$secId}.{$itemId}";
                }
            }
        }

        if (! empty($faltantes)) {
            throw new \LogicException(
                'Faltan respuestas para los siguientes ítems: '.implode(', ', $faltantes)
            );
        }

        $this->calcularScores();
        $this->asignarInterpretacion();
        $this->estado = EstadoPase::Completado;
        $this->save();
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Instrumento aplicado en este pase.
     *
     * @return BelongsTo<TipoEscala, self>
     */
    public function tipoEscala(): BelongsTo
    {
        return $this->belongsTo(TipoEscala::class, 'tipo_escala_id');
    }

    /**
     * Historia social del ciudadano al que se aplica la escala.
     *
     * @return BelongsTo<HistoriaSocial, self>
     */
    /**
     * ID del ciudadano titular de la historia social asociada al pase.
     *
     * @return int|null
     */
    public function getCiudadanoId(): ?int
    {
        return $this->historia?->ciudadano_id;
    }

    public function historia(): BelongsTo
    {
        return $this->belongsTo(HistoriaSocial::class, 'historia_id');
    }

    /**
     * Profesional que aplicó la escala.
     *
     * @return BelongsTo<User, self>
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesional_id');
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    protected static function newFactory(): PaseEscalaFactory
    {
        return PaseEscalaFactory::new();
    }
}
