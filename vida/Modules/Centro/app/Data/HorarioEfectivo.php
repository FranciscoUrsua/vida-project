<?php

namespace Modules\Centro\Data;

use Illuminate\Support\Carbon;

/**
 * Horario semanal efectivo de un centro en una fecha: el propio o el heredado del tipo.
 *
 * Los parámetros de agenda (buffers, tipos de slot, modo) siguen en el HorarioCentro.
 */
final readonly class HorarioEfectivo
{
    /**
     * @param list<int> $diasLaborables ISO: 1 = lunes … 7 = domingo.
     * @param string $apertura HH:MM.
     * @param string $cierre HH:MM.
     * @param string $inicioAtencion HH:MM.
     * @param string $finAtencion HH:MM.
     * @param string $origen 'tipo' | 'centro'.
     */
    public function __construct(
        public array $diasLaborables,
        public string $apertura,
        public string $cierre,
        public string $inicioAtencion,
        public string $finAtencion,
        public string $origen,
    ) {}

    /**
     * Si el día de la semana de la fecha es laborable en este horario.
     *
     * @param Carbon $fecha
     *
     * @return bool
     */
    public function abreEl(Carbon $fecha): bool
    {
        return in_array($fecha->isoWeekday(), $this->diasLaborables, true);
    }
}
