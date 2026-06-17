<?php

namespace Modules\Agenda\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Models\Slot;

/**
 * Actualiza el estado de los slots cuya hora ha pasado al final del día.
 *
 * - Pasa a 'expirado' los slots en estado 'bloqueado_urgencia' cuya hora ha pasado.
 * - Pasa a 'no_ocupado' los slots en estado 'disponible' no reservados cuya hora ha pasado.
 * - Pasa a 'no_ocupado' los slots en estado 'reservado' de fechas pasadas sin cita activa
 *   (no-shows de ciudadano cuyo slot no fue liberado a tiempo).
 *
 * No realiza ninguna acción sobre profesionales ni citas.
 * Se ejecuta diariamente al final del día laboral vía scheduler.
 */
class SlotExpirationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Transiciona los slots expirados a sus estados finales.
     *
     * Opera solo sobre fechas estrictamente anteriores a hoy para evitar
     * afectar slots del día en curso que aún pueden ser consumidos.
     *
     * @return void
     */
    public function handle(): void
    {
        $hoy = now()->toDateString();

        Slot::where('fecha', '<', $hoy)
            ->where('estado', EstadoSlot::BloqueadoUrgencia->value)
            ->update(['estado' => EstadoSlot::Expirado->value]);

        Slot::where('fecha', '<', $hoy)
            ->where('estado', EstadoSlot::Disponible->value)
            ->update(['estado' => EstadoSlot::NoOcupado->value]);

        // Slots reservados sin cita activa (no-show de ciudadano no procesado)
        $sinCitaActiva = Slot::where('fecha', '<', $hoy)
            ->where('estado', EstadoSlot::Reservado->value)
            ->whereDoesntHave('cita', fn ($q) => $q->whereIn('estado', ['confirmada', 'completada']))
            ->pluck('id');

        if ($sinCitaActiva->isNotEmpty()) {
            Slot::whereIn('id', $sinCitaActiva)->update(['estado' => EstadoSlot::NoOcupado->value]);
        }
    }
}
