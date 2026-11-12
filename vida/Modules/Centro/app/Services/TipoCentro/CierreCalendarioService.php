<?php

namespace Modules\Centro\Services\TipoCentro;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Models\Cita;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\CierreCalendario;
use Modules\Centro\Models\TipoCentro;

/**
 * Alta de cierres de calendario de un tipo de centro o de un centro (§5.2).
 *
 * Un cierre no cancela citas ni slots: devuelve, por centro afectado, cuántas
 * citas confirmadas caen en sus fechas para avisar a quien lo registra.
 */
class CierreCalendarioService
{
    /**
     * Registra el cierre y calcula las citas confirmadas afectadas.
     *
     * @param TipoCentro|Centro $titular Tipo (afecta a todos sus centros) o centro.
     * @param array{fecha_desde: string, fecha_hasta: string, tipo: string, hora_inicio?: string|null, hora_fin?: string|null, motivo: string} $datos
     * @param User|null $actor Usuario que lo registra.
     *
     * @return array{cierre: CierreCalendario, avisos: list<string>} Avisos del tipo «3 citas afectadas en CSS Norte».
     */
    public function crear(TipoCentro|Centro $titular, array $datos, ?User $actor): array
    {
        $cierre = CierreCalendario::create(array_merge($datos, [
            'titular_type' => $titular->getMorphClass(),
            'titular_id' => $titular->getKey(),
            'creado_por_id' => $actor?->id,
        ]));

        $centros = $titular instanceof TipoCentro ? $titular->centros()->where('activo', true)->get() : collect([$titular]);

        $avisos = [];
        foreach ($centros as $centro) {
            $citas = Cita::where('centro_id', $centro->id)
                ->where('estado', EstadoCita::Confirmada)
                ->whereDate('fecha', '>=', Carbon::parse($datos['fecha_desde'])->toDateString())
                ->whereDate('fecha', '<=', Carbon::parse($datos['fecha_hasta'])->toDateString())
                ->count();

            if ($citas > 0) {
                $avisos[] = $citas.' '.($citas === 1 ? 'cita afectada' : 'citas afectadas')." en {$centro->nombre}";
            }
        }

        return ['cierre' => $cierre, 'avisos' => $avisos];
    }
}
