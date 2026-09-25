<?php

namespace Modules\Documentos\Services;

use Illuminate\Support\Facades\URL;
use Modules\Documentos\Contracts\AlmacenDocumentos;
use Modules\Documentos\Exceptions\IntegridadDocumentoException;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\DocumentoVersion;
use Modules\Documentos\Services\Almacenamiento\CifradorDocumentos;

/**
 * Lectura de versiones de documento: descifra en memoria y comprueba la integridad.
 *
 * El contenido nunca se sirve desde el almacenamiento: toda descarga pasa por la
 * ruta de la aplicación documentos.ver (URL firmada temporal) y usa un nombre
 * genérico, nunca el nombre original.
 */
class LecturaDocumentoService
{
    /**
     * Inyecta el almacén y el cifrador.
     *
     * @param AlmacenDocumentos $almacen Almacén de objetos cifrados.
     * @param CifradorDocumentos $cifrador Cifrado por versión.
     */
    public function __construct(
        private readonly AlmacenDocumentos $almacen,
        private readonly CifradorDocumentos $cifrador,
    ) {}

    /**
     * PDF en claro de una versión, verificado contra su hash.
     *
     * @param DocumentoVersion $version Versión con contenido (vigente o sustituida).
     *
     * @throws IntegridadDocumentoException si falta, no se descifra o no coincide con el hash
     *
     * @return string
     */
    public function contenido(DocumentoVersion $version): string
    {
        if (! $version->tieneContenido() || $version->clave_cifrada === null || $version->id_clave_maestra === null) {
            throw new IntegridadDocumentoException("La versión #{$version->id} no tiene contenido.");
        }

        if (! $this->almacen->existe($version->clave_almacenamiento, $version->disco)) {
            throw new IntegridadDocumentoException("Falta el objeto de la versión #{$version->id}.");
        }

        $claro = $this->cifrador->descifrar(
            $this->almacen->leer($version->clave_almacenamiento, $version->disco),
            $version->clave_cifrada,
            $version->id_clave_maestra,
        );

        if (! hash_equals($version->hash_sha256, hash('sha256', $claro))) {
            throw new IntegridadDocumentoException("La versión #{$version->id} no coincide con su hash.");
        }

        return $claro;
    }

    /**
     * Indica si una versión se puede leer y coincide con su hash.
     *
     * @param DocumentoVersion $version Versión que se comprueba.
     *
     * @return bool
     */
    public function verificarIntegridad(DocumentoVersion $version): bool
    {
        try {
            $this->contenido($version);

            return true;
        } catch (IntegridadDocumentoException) {
            return false;
        }
    }

    /**
     * URL firmada temporal para visualizar el documento en el navegador (nunca al objeto del almacén).
     *
     * @param Documento $documento Documento cuya versión vigente se descargará.
     * @param int $minutos Minutos de validez.
     *
     * @return string
     */
    public function urlTemporal(Documento $documento, int $minutos = 30): string
    {
        return URL::temporarySignedRoute('documentos.ver', now()->addMinutes($minutos), ['documento' => $documento->id]);
    }

    /**
     * URL firmada temporal a la ruta de descarga como fichero (auditada como «exportar»).
     *
     * @param Documento $documento Documento cuya versión vigente se descargará.
     * @param int $minutos Minutos de validez.
     *
     * @return string
     */
    public function urlDescarga(Documento $documento, int $minutos = 30): string
    {
        return URL::temporarySignedRoute('documentos.descargar', now()->addMinutes($minutos), ['documento' => $documento->id]);
    }

    /**
     * Nombre genérico de descarga: código del tipo y fecha de captura.
     *
     * @param DocumentoVersion $version Versión descargada.
     *
     * @return string
     */
    public function nombreDescarga(DocumentoVersion $version): string
    {
        return $version->documento->tipo->codigo.'-'.$version->fecha_captura->format('Y-m-d').'.pdf';
    }
}
