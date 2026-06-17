<?php

namespace Modules\Agenda\Observers;

use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\ExcepcionProfesional;
use Modules\Agenda\Models\LineaCuadrante;
use Modules\Agenda\Models\Slot;

/**
 * Observer del modelo ExcepcionProfesional.
 *
 * Cuando se registra una excepción posterior a la publicación del cuadrante
 * y afecta a la disponibilidad, propaga el efecto sobre las líneas, slots
 * y citas ya materializadas (PF-07.5).
 */
class ExcepcionProfesionalObserver
{
    /**
     * Anula las líneas y slots del cuadrante publicado afectados por la excepción.
     *
     * Solo actúa si `afecta_disponibilidad = true`. Cancela además las citas
     * confirmadas vinculadas a los slots de esas líneas; los slots en estado
     * 'reservado' (con cita activa) no se anulan para preservar la trazabilidad.
     *
     * @param ExcepcionProfesional $excepcion Excepción profesional creada.
     *
     * @return void
     */
    public function created(ExcepcionProfesional $excepcion): void
    {
        if (! $excepcion->afecta_disponibilidad) {
            return;
        }

        $fechaInicioStr = $excepcion->fecha_inicio->toDateString();
        $fechaFinStr = $excepcion->fecha_fin->toDateString();

        // Líneas no anuladas del profesional en el rango de la excepción
        $lineaIds = LineaCuadrante::where('usuario_id', $excepcion->usuario_id)
            ->where('centro_id', $excepcion->centro_id)
            ->where('fecha', '>=', $fechaInicioStr)
            ->where('fecha', '<=', $fechaFinStr)
            ->where('anulada', false)
            ->pluck('id');

        if ($lineaIds->isEmpty()) {
            return;
        }

        LineaCuadrante::whereIn('id', $lineaIds)->update([
            'anulada' => true,
            'excepcion_id' => $excepcion->id,
        ]);

        // Todos los slot_ids en esas líneas (para la cancelación de citas)
        $todosLosSlotIds = Slot::whereIn('linea_cuadrante_id', $lineaIds)->pluck('id');

        // Cancelar las citas confirmadas vinculadas a esos slots
        if ($todosLosSlotIds->isNotEmpty()) {
            Cita::whereIn('slot_id', $todosLosSlotIds)
                ->where('estado', EstadoCita::Confirmada->value)
                ->update([
                    'estado' => EstadoCita::Cancelada->value,
                    'motivo_cancelacion' => 'Excepción del profesional',
                ]);
        }

        // Anular solo los slots disponibles y de urgencia; los reservados se dejan intactos
        $slotIdsAnulables = Slot::whereIn('linea_cuadrante_id', $lineaIds)
            ->whereIn('estado', [EstadoSlot::Disponible->value, EstadoSlot::BloqueadoUrgencia->value])
            ->pluck('id');

        if ($slotIdsAnulables->isNotEmpty()) {
            Slot::whereIn('id', $slotIdsAnulables)->update(['estado' => EstadoSlot::Anulado->value]);
        }
    }
}
