<?php

namespace Modules\Documentos\Exceptions;

use RuntimeException;

/**
 * La tubería de entrada rechazó un fichero. No queda nada ni en BBDD ni en disco.
 *
 * El código de motivo es estable (para tests y registros); el mensaje está en
 * castellano llano, sin detalles técnicos, para mostrarlo al profesional.
 */
class IngestaRechazadaException extends RuntimeException
{
    /**
     * Mensajes al usuario por código de motivo.
     *
     * @var array<string, string>
     */
    private const MENSAJES = [
        'formato_no_admitido' => 'Este tipo de fichero no se admite. Sube un PDF, una imagen (JPG, PNG, HEIC) o un documento ODT o DOCX.',
        'virus_detectado' => 'El fichero contiene un virus y no se ha guardado.',
        'antivirus_no_disponible' => 'No se ha podido comprobar el fichero con el antivirus. Inténtalo de nuevo más tarde.',
        'macros_no_admitidas' => 'El documento contiene macros y no se admite. Guárdalo sin macros o como PDF.',
        'conversion_fallida' => 'No se ha podido convertir el fichero a PDF. Comprueba que se abre bien o súbelo como PDF.',
        'pdf_protegido' => 'El PDF está protegido con contraseña. Sube una copia sin protección.',
        'pdf_no_normalizable' => 'No se ha podido procesar el PDF. Comprueba que no esté dañado.',
        'demasiadas_paginas' => 'El documento tiene demasiadas páginas para este tipo de documento.',
        'tamanyo_excedido' => 'El fichero es demasiado grande para este tipo de documento.',
        'fichero_no_legible' => 'No se ha podido leer el fichero.',
    ];

    /**
     * Crea el rechazo a partir de su código de motivo.
     *
     * @param string $codigo Código de motivo (clave de MENSAJES).
     * @param \Throwable|null $previa Causa técnica, solo para registros.
     */
    public function __construct(public readonly string $codigo, ?\Throwable $previa = null)
    {
        parent::__construct(self::MENSAJES[$codigo] ?? 'No se ha podido guardar el documento.', 0, $previa);
    }
}
