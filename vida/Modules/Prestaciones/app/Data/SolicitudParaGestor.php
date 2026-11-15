<?php

namespace Modules\Prestaciones\Data;

/**
 * Lo que se envía al gestor al iniciar un expediente: procedimiento, titular, parámetros y documentos.
 */
final readonly class SolicitudParaGestor
{
    /**
     * @param int $solicitudId Identificador en VIDA.
     * @param string|null $codigoGestor Código del procedimiento en el gestor.
     * @param int $ciudadanoId Titular.
     * @param array<string, mixed> $parametros Datos del formulario.
     * @param list<int> $documentoIds Documentos vinculados a la solicitud.
     */
    public function __construct(
        public int $solicitudId,
        public ?string $codigoGestor,
        public int $ciudadanoId,
        public array $parametros,
        public array $documentoIds,
    ) {}
}
