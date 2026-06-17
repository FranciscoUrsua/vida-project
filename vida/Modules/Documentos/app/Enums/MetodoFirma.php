<?php

namespace Modules\Documentos\Enums;

/**
 * Metodos de firma admitidos para documentos generados.
 */
enum MetodoFirma: string
{
    case AutofirmaCertificadoEmpleadoPublico = 'autofirma_certificado_empleado_publico';

    /**
     * Etiqueta legible para mostrar el metodo de firma.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::AutofirmaCertificadoEmpleadoPublico => 'AutoFirma — Certificado de Empleado Público',
        };
    }
}
