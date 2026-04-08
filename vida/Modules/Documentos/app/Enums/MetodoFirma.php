<?php

namespace Modules\Documentos\Enums;

enum MetodoFirma: string
{
    case AutofirmaCertificadoEmpleadoPublico = 'autofirma_certificado_empleado_publico';

    public function label(): string
    {
        return match ($this) {
            self::AutofirmaCertificadoEmpleadoPublico => 'AutoFirma — Certificado de Empleado Público',
        };
    }
}
