<?php

namespace Modules\Agenda\Services\Citas\CitaPrevia;

/**
 * Cita recibida del canal externo, ya traducida por el adaptador.
 *
 * `identificacion` son los datos de la persona tal como llegan (documento,
 * nombre, contacto): se guardan cifrados en la cita si no se puede identificar.
 */
final class CitaExternaRecibida
{
    /**
     * @param int $slotId
     * @param string $referenciaExterna Identificador de la cita en el sistema externo.
     * @param int $tipoCitaId
     * @param string|null $tipoDocumento dni | nie | pasaporte
     * @param string|null $numeroDocumento
     * @param array<string, mixed> $identificacion
     */
    public function __construct(
        public readonly int $slotId,
        public readonly string $referenciaExterna,
        public readonly int $tipoCitaId,
        public readonly ?string $tipoDocumento,
        public readonly ?string $numeroDocumento,
        public readonly array $identificacion,
    ) {}
}
