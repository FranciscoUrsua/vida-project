<?php

namespace Modules\Intervencion\Services\Asignacion;

use Modules\Centro\Models\Centro;

/**
 * Lo que impide a un profesional salir de un centro (RN-21): casos de los que es
 * referencia en el centro y citas confirmadas futuras en sus slots del centro.
 */
final class PendientesSalida
{
    /**
     * @param Centro $centro Centro del que sale.
     * @param int $casos Asignaciones de referencia vigentes en el centro.
     * @param int $citas Citas confirmadas desde hoy en el centro.
     */
    public function __construct(
        public readonly Centro $centro,
        public readonly int $casos,
        public readonly int $citas,
    ) {}

    /**
     * Si no queda nada pendiente y la salida es posible.
     *
     * @return bool
     */
    public function libre(): bool
    {
        return $this->casos === 0 && $this->citas === 0;
    }

    /**
     * Descripción de lo pendiente en este centro, p. ej. «CIAM Usera: 3 casos y 1 cita».
     *
     * @return string
     */
    public function descripcion(): string
    {
        $partes = [];

        if ($this->casos > 0) {
            $partes[] = $this->casos.' '.($this->casos === 1 ? 'caso' : 'casos').' de referencia';
        }

        if ($this->citas > 0) {
            $partes[] = $this->citas.' '.($this->citas === 1 ? 'cita confirmada' : 'citas confirmadas');
        }

        return $this->centro->nombre.': '.implode(' y ', $partes);
    }
}
