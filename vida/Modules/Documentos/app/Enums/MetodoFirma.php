<?php

namespace Modules\Documentos\Enums;

/**
 * Métodos de firma admitidos para un informe profesional (fase 5, F5-1).
 *
 * El código decide con ellos qué firmador se usa y qué se guarda en el informe.
 */
enum MetodoFirma: string
{
    case AutofirmaCertificadoEmpleadoPublico = 'autofirma_certificado_empleado_publico';
    case PdfFirmadoExternamente = 'pdf_firmado_externamente';

    /**
     * Etiqueta legible para mostrar el método de firma.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::AutofirmaCertificadoEmpleadoPublico => 'AutoFirma — Certificado de Empleado Público',
            self::PdfFirmadoExternamente => 'PDF firmado fuera de VIDA y subido por el autor',
        };
    }
}
