<?php

namespace Modules\Agenda\Services;

use Carbon\Carbon;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\LineaCuadrante;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Models\TipoSlot;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;

/**
 * Materializa los slots al publicar un CuadranteMes.
 *
 * Aplica reglas de HorarioCentro (buffers, horario de atención, días laborables)
 * y de cada TipoSlot (duración, porcentaje_urgencias).
 * Los slots de urgencia se crean directamente en estado 'bloqueado_urgencia'.
 */
class SlotMaterializadorService
{
    /**
     * Genera todos los slots de las líneas activas del cuadrante publicado.
     *
     * @param CuadranteMes $cuadrante Cuadrante (idealmente en estado 'publicado')
     *
     * @return int Número de slots creados
     */
    public function materializar(CuadranteMes $cuadrante): int
    {
        $primerDia = Carbon::create($cuadrante->anyo, $cuadrante->mes, 1)->toDateString();

        $horario = HorarioCentro::where('centro_id', $cuadrante->centro_id)
            ->where('activo', true)
            ->where('vigente_desde', '<=', $primerDia)
            ->where(function ($q) use ($primerDia) {
                $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $primerDia);
            })
            ->first();

        if (! $horario) {
            return 0;
        }

        $tiposSlot = $horario->tiposSlot()->where('activo', true)->get();

        if ($tiposSlot->isEmpty()) {
            return 0;
        }

        // Horario de cada día con herencia del tipo y cierres de calendario (docs/modulo-tipo-centro.md §5):
        // un día cerrado no tiene slots y uno con horario reducido se recorta a su franja
        $config = app(ConfiguracionEfectivaCentro::class);
        $centro = Centro::findOrFail($cuadrante->centro_id);
        $dias = [];

        $slotsParaCrear = [];
        $now = now();

        foreach ($cuadrante->lineas()->activas()->get() as $linea) {
            $fecha = Carbon::parse($linea->fecha);
            $dia = $dias[$fecha->toDateString()] ??= $config->dia($centro, $fecha);
            if (! $dia->abierto) {
                continue;
            }

            // Ventana de atención efectiva en minutos desde medianoche, con los márgenes del horario
            $atencionInicioMin = $this->toMinutes($dia->inicioAtencion) + $horario->buffer_inicio_minutos;
            $atencionFinMin = $this->toMinutes($dia->finAtencion) - $horario->buffer_fin_minutos;

            foreach ($tiposSlot as $tipoSlot) {
                foreach ($linea->franjas as $franja) {
                    $franjaInicioMin = $this->toMinutes($franja['inicio']);
                    $franjaFinMin = $this->toMinutes($franja['fin']);

                    // Intersect the professional's franja with the center's effective window
                    $ventanaInicioMin = max($franjaInicioMin, $atencionInicioMin);
                    $ventanaFinMin = min($franjaFinMin, $atencionFinMin);

                    if ($ventanaInicioMin >= $ventanaFinMin) {
                        continue;
                    }

                    $minutosDisponibles = $ventanaFinMin - $ventanaInicioMin;
                    $totalSlots = intdiv($minutosDisponibles, $tipoSlot->duracion_minutos);

                    if ($totalSlots === 0) {
                        continue;
                    }

                    $slotsUrgencia = (int) floor($totalSlots * $tipoSlot->porcentaje_urgencias / 100);
                    $slotsNormales = $totalSlots - $slotsUrgencia;

                    $cursorMin = $ventanaInicioMin;

                    for ($i = 0; $i < $slotsNormales; $i++) {
                        $slotsParaCrear[] = $this->buildRow($linea, $tipoSlot, $cursorMin, EstadoSlot::Disponible, $now);
                        $cursorMin += $tipoSlot->duracion_minutos;
                    }

                    for ($i = 0; $i < $slotsUrgencia; $i++) {
                        $slotsParaCrear[] = $this->buildRow($linea, $tipoSlot, $cursorMin, EstadoSlot::BloqueadoUrgencia, $now);
                        $cursorMin += $tipoSlot->duracion_minutos;
                    }
                }
            }
        }

        if (! empty($slotsParaCrear)) {
            Slot::insert($slotsParaCrear);
        }

        return count($slotsParaCrear);
    }

    private function buildRow(
        LineaCuadrante $linea,
        TipoSlot $tipoSlot,
        int $inicioMin,
        EstadoSlot $estado,
        \Illuminate\Support\Carbon $now
    ): array {
        return [
            'linea_cuadrante_id' => $linea->id,
            'usuario_id' => $linea->usuario_id,
            'centro_id' => $linea->centro_id,
            'tipo_slot_id' => $tipoSlot->id,
            'fecha' => $linea->fecha->toDateString(),
            'hora_inicio' => $this->fromMinutes($inicioMin),
            'hora_fin' => $this->fromMinutes($inicioMin + $tipoSlot->duracion_minutos),
            'estado' => $estado->value,
            'espacio_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /** Converts "HH:MM" or "HH:MM:SS" to minutes since midnight. */
    private function toMinutes(string $time): int
    {
        [$h, $m] = explode(':', $time);

        return (int) $h * 60 + (int) $m;
    }

    /** Converts minutes since midnight to "HH:MM". */
    private function fromMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
