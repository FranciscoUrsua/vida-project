<?php

namespace Modules\Documentos\Services\Firma;

use Modules\Documentos\Contracts\FirmadorInforme;
use Modules\Documentos\Enums\MetodoFirma;
use Modules\Documentos\Exceptions\FirmaRechazadaException;
use Modules\Documentos\Models\Informe;

/**
 * Firma con AutoFirma integrada desde el navegador (F5-1 b, preparada e inactiva).
 *
 * El navegador pide a AutoFirma (cliente JavaScript `autoscript.js`, protocolo
 * `afirma://`) que firme en PAdES el PDF para firmar con el certificado del empleado
 * público; AutoFirma devuelve el PDF firmado en base64 y Livewire lo envía aquí.
 *
 * Se activa con `documentos.firma.autofirma_activa` (`DOCUMENTOS_AUTOFIRMA_ACTIVA`)
 * cuando se haya probado en puestos reales con certificado. Mientras esté a `false`,
 * no se ofrece en la interfaz y rechaza toda petición. Cómo probarla:
 * docs/decisiones-tecnicas.md («Firma de informes»).
 */
class FirmaAutoFirma implements FirmadorInforme
{
    /**
     * Método de firma que se guarda en el informe.
     *
     * @return MetodoFirma
     */
    public function metodo(): MetodoFirma
    {
        return MetodoFirma::AutofirmaCertificadoEmpleadoPublico;
    }

    /**
     * Activa solo por configuración.
     *
     * @return bool
     */
    public function activo(): bool
    {
        return (bool) config('documentos.firma.autofirma_activa', false);
    }

    /**
     * Decodifica el PDF firmado en base64 que devuelve AutoFirma.
     *
     * @param Informe $informe Informe que se firma.
     * @param mixed $entrada PDF firmado en base64.
     *
     * @return string
     *
     * @throws FirmaRechazadaException metodo_inactivo o pdf_ilegible
     */
    public function pdfFirmado(Informe $informe, mixed $entrada): string
    {
        if (! $this->activo()) {
            throw new FirmaRechazadaException('metodo_inactivo');
        }

        $contenido = is_string($entrada) ? base64_decode($entrada, true) : false;

        if ($contenido === false || $contenido === '') {
            throw new FirmaRechazadaException('pdf_ilegible');
        }

        return $contenido;
    }
}
