<?php

namespace Modules\Intervencion\Services;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\PlanObjetivo;
use Modules\Intervencion\Models\PlanObjetivoIndicador;
use Modules\Intervencion\Models\SeguimientoPlan;

/**
 * Evaluación de los objetivos del plan en cada seguimiento (fase 3, F3-7, bloque C).
 *
 * El profesional valora los indicadores (opcional, uno a uno); por cada objetivo valorado
 * se le propone un estado y lo confirma o lo cambia. El seguimiento, las valoraciones
 * (con su historial) y los estados de los objetivos se guardan en una sola transacción.
 */
class EvaluacionObjetivosService
{
    /** Estados válidos de un objetivo. */
    public const ESTADOS_OBJETIVO = ['pendiente', 'en_proceso', 'conseguido', 'abandonado'];

    /**
     * Estado del objetivo que se propone a partir de la valoración de su indicador (F3-7).
     *
     * `conseguido` y `si` proponen conseguido; `en_proceso`, `se_mantiene` y `favorable`,
     * en proceso; `no_conseguido`, `desfavorable` y `no`, ninguno. `abandonado` nunca se
     * propone: siempre lo decide el profesional.
     *
     * @param string $valoracion
     *
     * @return string|null
     */
    public function propuestaDeEstado(string $valoracion): ?string
    {
        return match ($valoracion) {
            'conseguido', 'si' => 'conseguido',
            'en_proceso', 'se_mantiene', 'favorable' => 'en_proceso',
            default => null,
        };
    }

    /**
     * Registra un seguimiento del plan con la evaluación de sus objetivos.
     *
     * @param PlanDeIntervencion $plan Plan que el actor puede editar.
     * @param array<string, mixed> $datosSeguimiento Campos de SeguimientoPlan (sin plan ni profesional).
     * @param array<int|string, string|null> $valoraciones Valor por id de indicador; vacío = sin valorar.
     * @param array<int|string, string|null> $estados Estado confirmado por id de objetivo; vacío = sin cambio.
     * @param User $actor
     *
     * @return SeguimientoPlan
     *
     * @throws DomainException Si el actor no puede editar el plan o un indicador u objetivo no es suyo.
     * @throws \InvalidArgumentException Si una valoración no es válida para su indicador.
     */
    public function registrarSeguimiento(PlanDeIntervencion $plan, array $datosSeguimiento, array $valoraciones, array $estados, User $actor): SeguimientoPlan
    {
        if (! Gate::forUser($actor)->allows('update', $plan)) {
            throw new DomainException('No puedes registrar seguimientos de este plan.');
        }

        return DB::transaction(function () use ($plan, $datosSeguimiento, $valoraciones, $estados, $actor): SeguimientoPlan {
            $seguimiento = SeguimientoPlan::create($datosSeguimiento + [
                'plan_id' => $plan->id,
                'profesional_id' => $actor->id,
                'fecha' => today()->toDateString(),
            ]);

            foreach (array_filter($valoraciones, fn ($valor) => $valor !== null && $valor !== '') as $indicadorId => $valor) {
                $indicador = PlanObjetivoIndicador::with('planObjetivo')->findOrFail($indicadorId);

                if ($indicador->planObjetivo->plan_id !== $plan->id) {
                    throw new DomainException('Un indicador no es de este plan.');
                }

                $indicador->registrarValoracion($valor, $seguimiento->id, $actor->id);
            }

            foreach (array_filter($estados, fn ($estado) => $estado !== null && $estado !== '') as $objetivoId => $estado) {
                if (! in_array($estado, self::ESTADOS_OBJETIVO, true)) {
                    throw new DomainException("Estado de objetivo no válido: {$estado}.");
                }

                $objetivo = PlanObjetivo::where('plan_id', $plan->id)->findOrFail($objetivoId);

                if ($objetivo->estado !== $estado) {
                    $objetivo->update(['estado' => $estado]);
                }
            }

            return $seguimiento;
        });
    }
}
