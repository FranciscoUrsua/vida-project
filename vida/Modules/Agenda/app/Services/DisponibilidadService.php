<?php

namespace Modules\Agenda\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Calcula los slots disponibles para un profesional en un período.
 *
 * Consulta slots materializados filtrando por estado 'disponible'.
 * Usa spatie/period para operaciones de intersección con excepciones.
 */
class DisponibilidadService
{
    /**
     * Obtiene los slots disponibles de un profesional en el período indicado.
     *
     * @param  int     $usuarioId        ID del profesional
     * @param  int     $centroId         ID del centro
     * @param  int     $tipoSlotId       ID del tipo de slot
     * @param  Carbon  $desde            Fecha de inicio del período
     * @param  Carbon  $hasta            Fecha de fin del período
     * @param  bool    $incluirUrgencias Si se incluyen los slots de urgencia
     * @return Collection<\Modules\Agenda\Models\Slot>
     */
    public function obtenerSlots(
        int $usuarioId,
        int $centroId,
        int $tipoSlotId,
        Carbon $desde,
        Carbon $hasta,
        bool $incluirUrgencias = false
    ): Collection {
        // TODO: implementar en fase de interfaz Livewire
        return collect();
    }
}
