<?php

namespace Modules\Ciudadania\Services;

/**
 * DTO inmutable que representa un candidato de posible duplicado.
 *
 * @property int $ciudadanoId
 * @property string $nombreCompleto
 * @property string|null $documento
 * @property string|null $fechaNacimiento
 * @property float $score Score de similitud [0.0 – 1.0].
 * @property list<string> $camposCoincidentes Campos que determinaron la similitud.
 * @property bool $bloquea true si el score supera el umbral de bloqueo.
 */
readonly class ResultadoMatching
{
    /**
     * @param list<string> $camposCoincidentes
     */
    public function __construct(
        public int $ciudadanoId,
        public string $nombreCompleto,
        public ?string $documento,
        public ?string $fechaNacimiento,
        public float $score,
        public array $camposCoincidentes,
        public bool $bloquea,
    ) {}
}
