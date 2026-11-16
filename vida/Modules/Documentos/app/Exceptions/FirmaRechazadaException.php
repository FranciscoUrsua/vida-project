<?php

namespace Modules\Documentos\Exceptions;

use RuntimeException;

/**
 * El PDF firmado de un informe no supera la validación mínima (fase 5, F5-2).
 *
 * El informe no cambia de estado. El código de motivo es estable (tests y registros);
 * el mensaje, en castellano llano y sin detalles técnicos, se muestra al profesional.
 * Si la causa es un fallo de una herramienta externa, viaja como excepción previa
 * (`FalloHerramientaExterna`) para registrarla en el log.
 */
class FirmaRechazadaException extends RuntimeException
{
    /**
     * Mensajes al usuario por código de motivo.
     *
     * @var array<string, string>
     */
    private const MENSAJES = [
        'sin_firma' => 'El PDF no está firmado. Fírmalo con tu certificado y vuelve a subirlo.',
        'firma_no_integra' => 'La firma del PDF no es válida: el documento se ha modificado después de firmarlo. Firma de nuevo el PDF descargado de VIDA.',
        'no_corresponde' => 'El PDF no corresponde a este informe. Descarga de nuevo el PDF para firmar y súbelo sin cambios.',
        'es_borrador' => 'Has subido la vista previa del borrador. Descarga el «PDF para firmar» y firma ese.',
        'pdf_ilegible' => 'No se ha podido leer el PDF. Comprueba que no esté dañado.',
        'fallo_tecnico' => 'No se ha podido comprobar la firma del PDF. Inténtalo de nuevo más tarde.',
        'metodo_inactivo' => 'Este método de firma no está disponible.',
    ];

    /**
     * Crea el rechazo a partir de su código de motivo.
     *
     * @param string $codigo Código de motivo (clave de MENSAJES).
     * @param \Throwable|null $previa Causa técnica, solo para registros.
     */
    public function __construct(public readonly string $codigo, ?\Throwable $previa = null)
    {
        parent::__construct(self::MENSAJES[$codigo] ?? 'No se ha podido firmar el informe.', 0, $previa);
    }

    /**
     * Indica si el rechazo se debe a un fallo técnico que hay que registrar.
     *
     * @return bool
     */
    public function tieneCausaTecnica(): bool
    {
        return $this->getPrevious() instanceof FalloHerramientaExterna;
    }
}
