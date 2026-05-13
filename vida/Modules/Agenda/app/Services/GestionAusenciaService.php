<?php

namespace Modules\Agenda\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\ReasignacionCita;
use Modules\Agenda\Models\Slot;

/**
 * Gestiona el flujo cuando un profesional no se presenta.
 * - Las citas confirmadas del día pasan a estado 'cancelada' con motivo descriptivo.
 * - Los slots disponibles y bloqueado_urgencia de esas fechas pasan a estado 'anulado'.
 * - Los slots en estado 'reservado' (con cita) no se anulan hasta que la cita sea cancelada.
 * - Genera alerta al supervisor con la lista de citas canceladas pendientes de reagendización.
 * - En modos estandar/avanzado, devuelve slots de urgencia disponibles para reasignación.
 * - En modo basico, devuelve slots disponibles de otros profesionales.
 */
class GestionAusenciaService
{
    /**
     * Procesa la ausencia de un profesional en una fecha concreta.
     *
     * @param  int    $usuarioId ID del profesional ausente
     * @param  int    $centroId  ID del centro
     * @param  Carbon $fecha     Fecha de la ausencia
     * @return Collection<Cita>  Citas pendientes de reasignación
     */
    public function procesarAusencia(int $usuarioId, int $centroId, Carbon $fecha): Collection
    {
        // TODO: implementar
        return collect();
    }

    /**
     * Reasigna una cita a un nuevo slot.
     *
     * Siempre la realiza un supervisor; el sistema asiste mostrando candidatos
     * pero la selección final es humana (Principio 3.9).
     *
     * @param  Cita   $cita        Cita en estado no_show_profesional
     * @param  Slot   $slotDestino Slot de urgencia del profesional sustituto
     * @param  int    $supervisorId ID del supervisor que autoriza la reasignación
     * @param  string $motivo      Valor del enum MotivoReasignacion
     * @return ReasignacionCita    Registro de la reasignación
     */
    public function reasignar(Cita $cita, Slot $slotDestino, int $supervisorId, string $motivo): ReasignacionCita
    {
        // TODO: implementar
        return new ReasignacionCita();
    }
}
