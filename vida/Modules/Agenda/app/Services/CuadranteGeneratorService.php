<?php

namespace Modules\Agenda\Services;

use Modules\Agenda\Models\CuadranteMes;

/**
 * Genera el borrador de LineaCuadrante para un mes.
 *
 * En modo básico, genera y publica automáticamente.
 * En modo estándar/avanzado, genera borrador para revisión del supervisor.
 * Usa simshaun/recurr para proyectar el horario habitual sobre el mes.
 */
class CuadranteGeneratorService
{
    /**
     * Genera un borrador de cuadrante a partir de los perfiles horarios del centro.
     *
     * @param  CuadranteMes $cuadrante Cuadrante en estado 'borrador'
     * @return void
     */
    public function generarBorrador(CuadranteMes $cuadrante): void
    {
        // TODO: implementar
    }

    /**
     * Genera y publica automáticamente el cuadrante de un mes.
     * Usado en modo básico: sin intervención del supervisor.
     *
     * @param  int         $centroId ID del centro
     * @param  int         $anyo     Año del cuadrante
     * @param  int         $mes      Mes del cuadrante (1-12)
     * @return CuadranteMes          Cuadrante en estado 'publicado'
     */
    public function generarYPublicarAutomaticamente(int $centroId, int $anyo, int $mes): CuadranteMes
    {
        // TODO: implementar — usado en modo básico
        return new CuadranteMes();
    }
}
