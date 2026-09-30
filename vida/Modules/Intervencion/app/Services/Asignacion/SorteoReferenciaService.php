<?php

namespace Modules\Intervencion\Services\Asignacion;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Centro\Models\Centro;
use Modules\Intervencion\Models\AsignacionProfesional;
use Random\Randomizer;

/**
 * Sorteo del profesional de referencia de una persona nueva, con corrección
 * del desvío.
 *
 * Reparte las entradas nuevas en proporción a la jornada, sin mirar cuántos
 * casos tiene cada uno (RN-05): así mantener casos dormidos no protege de
 * recibir casos y cerrar casos no se castiga. En la ventana de reparto se
 * compara lo que le correspondía a cada profesional (cada entrada se reparte
 * entre quienes estaban en el reparto ese día, según su peso) con lo que ha
 * recibido. Son candidatos quienes van por debajo; si nadie lo va, todos. Entre
 * ellos se sortea con probabilidad proporcional a la jornada: nadie puede
 * prever a quién le toca (no es un turno) y a medio plazo el reparto es exacto.
 *
 * La aleatoriedad llega inyectada para poder fijar la semilla en los tests.
 *
 * @see docs/modulo-asignacion.md §4.2
 */
class SorteoReferenciaService
{
    /** Tolerancia para comparar lo esperado (decimal) con lo recibido (entero). */
    private const EPSILON = 1e-9;

    /**
     * @param PoolReferenciaService $pool
     * @param Randomizer $azar
     */
    public function __construct(
        private readonly PoolReferenciaService $pool,
        private readonly Randomizer $azar,
    ) {}

    /**
     * Sortea el profesional de referencia del centro en una fecha. Solo decide;
     * no crea la asignación.
     *
     * @param Centro $centro
     * @param Carbon $fecha
     * @return ResultadoSorteo
     */
    public function sortear(Centro $centro, Carbon $fecha): ResultadoSorteo
    {
        $elegibles = $this->pool->elegibles($centro, $fecha);

        if ($elegibles->isEmpty()) {
            return ResultadoSorteo::vacio();
        }

        [$esperadas, $recibidas] = $this->balance($centro, $fecha);

        $profesionales = $elegibles->map(function (array $e) use ($esperadas, $recibidas) {
            $id = $e['usuario']->id;
            $esperado = $esperadas[$id] ?? 0.0;
            $recibido = $recibidas[$id] ?? 0;

            return [
                'usuario_id' => $id,
                'peso' => $e['peso'],
                'esperado' => round($esperado, 6),
                'recibido' => $recibido,
                'candidato' => $esperado - $recibido > self::EPSILON,
            ];
        });

        // Si nadie va por debajo de lo que le corresponde, todos son candidatos
        if (! $profesionales->contains('candidato', true)) {
            $profesionales = $profesionales->map(fn (array $p) => ['candidato' => true] + $p);
        }

        $elegidoId = $this->elegirPonderado($profesionales->where('candidato', true)->values());

        return new ResultadoSorteo(
            $elegibles->first(fn (array $e) => $e['usuario']->id === $elegidoId)['usuario'],
            $profesionales->values()->all(),
        );
    }

    /**
     * Instantánea del reparto de un día (profesionales y pesos), para guardar en
     * las asignaciones por elección, que también cuentan como entrada.
     *
     * @param Centro $centro
     * @param Carbon $fecha
     * @param int $elegidoId
     * @return array{profesionales: list<array{usuario_id: int, peso: float}>, elegido: int}
     */
    public function instantanea(Centro $centro, Carbon $fecha, int $elegidoId): array
    {
        return [
            'profesionales' => $this->pool->elegibles($centro, $fecha)
                ->map(fn (array $e) => ['usuario_id' => $e['usuario']->id, 'peso' => $e['peso']])
                ->all(),
            'elegido' => $elegidoId,
        ];
    }

    /**
     * Entradas esperadas y recibidas por profesional en la ventana de reparto.
     * Cada entrada se reparte entre los profesionales que estaban en el reparto
     * en su fecha (guardados en su campo `sorteo`), en proporción a su peso: quien
     * se incorpora no arrastra un déficit de lo que entró antes.
     *
     * @param Centro $centro
     * @param Carbon $fecha
     * @return array{0: array<int, float>, 1: array<int, int>}
     */
    private function balance(Centro $centro, Carbon $fecha): array
    {
        $esperadas = [];
        $recibidas = [];

        $entradas = AsignacionProfesional::query()
            ->where('centro_id', $centro->id)
            ->where('cuenta_en_reparto', true)
            ->whereDate('fecha_inicio', '>', $fecha->copy()->subMonths($centro->ventana_reparto_meses)->toDateString())
            ->whereDate('fecha_inicio', '<=', $fecha->toDateString())
            ->get(['profesional_id', 'sorteo']);

        foreach ($entradas as $entrada) {
            $pool = $entrada->sorteo['profesionales'] ?? [];
            $total = array_sum(array_column($pool, 'peso'));

            // Sin reparto registrado no se puede saber qué correspondía: no cuenta
            if ($total <= 0) {
                continue;
            }

            $recibidas[$entrada->profesional_id] = ($recibidas[$entrada->profesional_id] ?? 0) + 1;

            foreach ($pool as $p) {
                $esperadas[$p['usuario_id']] = ($esperadas[$p['usuario_id']] ?? 0.0) + $p['peso'] / $total;
            }
        }

        return [$esperadas, $recibidas];
    }

    /**
     * Elige un candidato con probabilidad proporcional a su peso.
     *
     * @param Collection<int, array{usuario_id: int, peso: float}> $candidatos
     * @return int Id del usuario elegido.
     */
    private function elegirPonderado(Collection $candidatos): int
    {
        // Pesos en centésimas de hora para sortear con enteros (jornadas como 17,5 h)
        $pesos = $candidatos->map(fn (array $c) => max(1, (int) round($c['peso'] * 100)));
        $tirada = $this->azar->getInt(1, $pesos->sum());

        foreach ($candidatos as $i => $candidato) {
            $tirada -= $pesos[$i];

            if ($tirada <= 0) {
                return $candidato['usuario_id'];
            }
        }

        return $candidatos->last()['usuario_id'];
    }
}
