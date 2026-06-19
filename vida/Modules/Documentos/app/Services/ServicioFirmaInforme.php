<?php

namespace Modules\Documentos\Services;

use Modules\Documentos\Enums\EstadoInforme;
use Modules\Documentos\Enums\MetodoFirma;
use Modules\Documentos\Models\Informe;

/**
 * Coordina el proceso de firma de un informe mediante AutoFirma.
 *
 * Flujo:
 * 1. El profesional genera la vista previa del PDF (ServicioGeneracionPDF::generarBorrador)
 * 2. El cliente AutoFirma firma el PDF con el Certificado de Empleado Público
 * 3. El componente Livewire recibe el PDF firmado en base64 y llama a este servicio
 * 4. El servicio persiste el PDF y actualiza el estado del informe
 *
 * La responsabilidad de autoría es personal: no existe fallback de firma manuscrita.
 */
class ServicioFirmaInforme
{
    /**
     * Crea el servicio con el generador de PDFs.
     *
     * @param ServicioGeneracionPDF $generacionPdf Generador de PDFs.
     */
    public function __construct(private ServicioGeneracionPDF $generacionPdf) {}

    /**
     * Firma un informe en estado borrador.
     *
     * @param Informe $informe Informe en estado borrador
     * @param string $pdfFirmadoBase64 PDF firmado por AutoFirma en base64
     *
     * @return Informe Informe actualizado a estado firmado
     *
     * @throws \DomainException si el informe no está en estado borrador
     */
    public function firmar(Informe $informe, string $pdfFirmadoBase64): Informe
    {
        if ($informe->estado !== EstadoInforme::Borrador) {
            throw new \DomainException(
                'Solo se puede firmar un informe en estado borrador. '.
                "El informe #{$informe->id} está en estado '{$informe->estado->label()}'."
            );
        }

        // TODO: integración AutoFirma — invocar API JavaScript del cliente desde Livewire
        // y recibir el PDF firmado. En esta fase se acepta directamente el PDF en base64.
        // Validación de firma CAdES y extracción de número de colegiado pendientes.

        $documento = $this->generacionPdf->generarFinal($informe, $pdfFirmadoBase64);

        $informe->update([
            'estado' => EstadoInforme::Firmado->value,
            'documento_id' => $documento->id,
            'firmado_en' => now(),
            'metodo_firma' => MetodoFirma::AutofirmaCertificadoEmpleadoPublico->value,
        ]);

        return $informe->fresh();
    }

    /**
     * Anula un informe firmado. Solo puede ejecutarlo el autor.
     *
     * @param Informe $informe Informe firmado que se anula.
     * @param int $usuarioId ID del usuario que solicita la anulación.
     * @param string $motivo Motivo de anulación.
     *
     * @throws \DomainException si el informe no está firmado o el usuario no es el autor
     */
    public function anular(Informe $informe, int $usuarioId, string $motivo): Informe
    {
        if (! $informe->estaFirmado()) {
            throw new \DomainException('Solo se pueden anular informes en estado firmado.');
        }

        if ($informe->autor_id !== $usuarioId) {
            throw new \DomainException('Solo el autor puede anular su propio informe.');
        }

        $informe->update([
            'estado' => EstadoInforme::Anulado->value,
            'motivo_anulacion' => $motivo,
            'anulado_en' => now(),
        ]);

        return $informe->fresh();
    }
}
