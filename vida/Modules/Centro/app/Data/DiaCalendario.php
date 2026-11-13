<?php

namespace Modules\Centro\Data;

/**
 * Cómo abre un centro un día concreto, con los cierres de calendario aplicados (§5.2).
 */
final readonly class DiaCalendario
{
    /**
     * @param bool $abierto Si el centro abre ese día.
     * @param string|null $apertura HH:MM, si abre.
     * @param string|null $cierre HH:MM, si abre.
     * @param string|null $inicioAtencion HH:MM, si abre.
     * @param string|null $finAtencion HH:MM, si abre.
     * @param string $origen 'horario' | 'cierre_tipo' | 'cierre_centro' | 'sin_horario'.
     */
    public function __construct(
        public bool $abierto,
        public ?string $apertura,
        public ?string $cierre,
        public ?string $inicioAtencion,
        public ?string $finAtencion,
        public string $origen,
    ) {}

    /**
     * Día cerrado.
     *
     * @param string $origen Motivo del cierre.
     *
     * @return self
     */
    public static function cerrado(string $origen): self
    {
        return new self(false, null, null, null, null, $origen);
    }
}
