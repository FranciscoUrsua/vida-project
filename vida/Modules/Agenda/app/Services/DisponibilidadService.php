<?php

namespace Modules\Agenda\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Models\Slot;

/**
 * Calcula los slots disponibles para un profesional en un período.
 *
 * Consulta slots materializados filtrando por estado y criterios de búsqueda.
 * Los slots de urgencia solo se incluyen cuando el canal solicitante los permite
 * explícitamente (canal interno, supervisores).
 */
class DisponibilidadService
{
    /**
     * Obtiene los slots disponibles de un profesional en el período indicado.
     *
     * Por defecto excluye los slots de urgencia, que solo son visibles
     * internamente. Pasar `$incluirUrgencias = true` para incluirlos
     * (canal interno, reasignaciones supervisadas).
     *
     * @param int $usuarioId ID del profesional
     * @param int $centroId ID del centro
     * @param int $tipoSlotId ID del tipo de slot
     * @param Carbon $desde Fecha de inicio del período (inclusive)
     * @param Carbon $hasta Fecha de fin del período (inclusive)
     * @param bool $incluirUrgencias Si se incluyen los slots de urgencia
     *
     * @return Collection<int, Slot>
     */
    public function obtenerSlots(
        int $usuarioId,
        int $centroId,
        int $tipoSlotId,
        Carbon $desde,
        Carbon $hasta,
        bool $incluirUrgencias = false
    ): Collection {
        return $this->obtenerSlotsDe([$usuarioId], $centroId, [$tipoSlotId], $desde, $hasta, $incluirUrgencias);
    }

    /**
     * Slots libres de varios profesionales y tipos de slot en un período, en una
     * sola consulta (búsqueda de huecos para citas).
     *
     * @param list<int>|null $usuarioIds Null: todos los profesionales del centro.
     * @param int $centroId
     * @param list<int> $tipoSlotIds
     * @param Carbon $desde Inclusive.
     * @param Carbon $hasta Inclusive.
     * @param bool $incluirUrgencias Si se incluyen los slots reservados para urgencias.
     * @return Collection<int, Slot>
     */
    public function obtenerSlotsDe(
        ?array $usuarioIds,
        int $centroId,
        array $tipoSlotIds,
        Carbon $desde,
        Carbon $hasta,
        bool $incluirUrgencias = false
    ): Collection {
        $estados = $incluirUrgencias
            ? [EstadoSlot::Disponible->value, EstadoSlot::BloqueadoUrgencia->value]
            : [EstadoSlot::Disponible->value];

        return Slot::query()
            ->when($usuarioIds !== null, fn ($q) => $q->whereIn('usuario_id', $usuarioIds))
            ->where('centro_id', $centroId)
            ->whereIn('tipo_slot_id', $tipoSlotIds)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereIn('estado', $estados)
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();
    }
}
