<?php

namespace Modules\Documentos\Services\Firma;

use Illuminate\Http\UploadedFile;
use Modules\Documentos\Contracts\FirmadorInforme;
use Modules\Documentos\Enums\MetodoFirma;
use Modules\Documentos\Exceptions\FirmaRechazadaException;
use Modules\Documentos\Models\Informe;

/**
 * Firma por subida de un PDF firmado fuera de VIDA (F5-1 a, la forma activa).
 *
 * El autor descarga el PDF para firmar, lo firma con su certificado en AutoFirma de
 * escritorio u otra herramienta y sube el resultado en el paso «Firma» del asistente.
 */
class FirmaExternaSubida implements FirmadorInforme
{
    /**
     * Método de firma que se guarda en el informe.
     *
     * @return MetodoFirma
     */
    public function metodo(): MetodoFirma
    {
        return MetodoFirma::PdfFirmadoExternamente;
    }

    /**
     * Siempre disponible: es la forma de firma por defecto.
     *
     * @return bool
     */
    public function activo(): bool
    {
        return true;
    }

    /**
     * Lee el PDF subido.
     *
     * @param Informe $informe Informe que se firma.
     * @param mixed $entrada Fichero subido (UploadedFile).
     *
     * @return string
     *
     * @throws FirmaRechazadaException pdf_ilegible si no llega un fichero legible
     */
    public function pdfFirmado(Informe $informe, mixed $entrada): string
    {
        if (! $entrada instanceof UploadedFile) {
            throw new FirmaRechazadaException('pdf_ilegible');
        }

        $contenido = @file_get_contents($entrada->getRealPath());

        if ($contenido === false || $contenido === '') {
            throw new FirmaRechazadaException('pdf_ilegible');
        }

        return $contenido;
    }
}
