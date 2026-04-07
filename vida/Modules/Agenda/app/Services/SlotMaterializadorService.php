<?php

namespace Modules\Agenda\Services;

use Modules\Agenda\Models\CuadranteMes;

/**
 * Materializa los slots al publicar un CuadranteMes.
 *
 * Al publicar un CuadranteMes, genera los registros Slot.
 * Aplica reglas de HorarioCentro (buffers) y TipoSlot (duración, porcentaje_urgencias).
 * Los slots de urgencia se crean directamente en estado 'bloqueado_urgencia'.
 */
class SlotMaterializadorService
{
    /**
     * Genera todos los slots del cuadrante publicado.
     *
     * @param  CuadranteMes $cuadrante Cuadrante en estado 'publicado'
     * @return int                     Número de slots creados
     */
    public function materializar(CuadranteMes $cuadrante): int
    {
        // TODO: implementar
        return 0;
    }
}
