<?php

namespace Modules\Documentos\Contracts;

use Modules\Documentos\Enums\MetodoFirma;
use Modules\Documentos\Exceptions\FirmaRechazadaException;
use Modules\Documentos\Models\Informe;

/**
 * Forma de obtener el PDF firmado de un informe (fase 5, F5-1).
 *
 * Cada implementación traduce lo que llega del navegador (un fichero subido, el
 * PDF en base64 que devuelve AutoFirma…) al contenido binario del PDF firmado. La
 * validación (F5-2) y la custodia son comunes y las hace `ServicioFirmaInforme`.
 */
interface FirmadorInforme
{
    /**
     * Método de firma que se guarda en el informe.
     *
     * @return MetodoFirma
     */
    public function metodo(): MetodoFirma;

    /**
     * Indica si este método se ofrece en la interfaz y admite peticiones.
     *
     * @return bool
     */
    public function activo(): bool;

    /**
     * Contenido binario del PDF firmado a partir de la entrada del navegador.
     *
     * @param Informe $informe Informe que se firma.
     * @param mixed $entrada Lo recibido del navegador (depende del método).
     *
     * @return string
     *
     * @throws FirmaRechazadaException si la entrada no es utilizable o el método no está activo
     */
    public function pdfFirmado(Informe $informe, mixed $entrada): string;
}
