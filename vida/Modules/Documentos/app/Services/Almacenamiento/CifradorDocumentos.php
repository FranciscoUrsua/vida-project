<?php

namespace Modules\Documentos\Services\Almacenamiento;

use Modules\Documentos\Contracts\ProveedorClavesMaestras;
use Modules\Documentos\Exceptions\IntegridadDocumentoException;

/**
 * Cifrado por versión de documento (envelope encryption).
 *
 * Cada versión se cifra con una clave de datos aleatoria de 256 bits (AES-256-GCM).
 * Esa clave se guarda cifrada con la clave maestra; destruirla deja el objeto
 * ilegible también en copias de seguridad (crypto-shredding).
 *
 * Formato del objeto: nonce (12 bytes) · tag (16 bytes) · texto cifrado.
 */
class CifradorDocumentos
{
    private const CIFRADO = 'aes-256-gcm';

    private const LONGITUD_CABECERA = 28;

    /**
     * Inyecta el proveedor de la clave maestra.
     *
     * @param ProveedorClavesMaestras $claves Custodio de la clave maestra.
     */
    public function __construct(private readonly ProveedorClavesMaestras $claves) {}

    /**
     * Cifra un contenido con una clave de datos nueva.
     *
     * @param string $claro Contenido en claro.
     *
     * @return array{contenido: string, clave_cifrada: string, id_clave_maestra: string}
     */
    public function cifrar(string $claro): array
    {
        $claveDatos = random_bytes(32);
        $nonce = random_bytes(12);
        $tag = '';
        $cifrado = openssl_encrypt($claro, self::CIFRADO, $claveDatos, OPENSSL_RAW_DATA, $nonce, $tag);
        $claveProtegida = $this->claves->cifrarClave($claveDatos);

        sodium_memzero($claveDatos);

        return [
            'contenido' => $nonce.$tag.$cifrado,
            'clave_cifrada' => $claveProtegida['clave_cifrada'],
            'id_clave_maestra' => $claveProtegida['id_clave_maestra'],
        ];
    }

    /**
     * Descifra un objeto con su clave de datos cifrada.
     *
     * @param string $contenido Objeto cifrado tal como está en el almacén.
     * @param string $claveCifrada Clave de datos cifrada de la versión.
     * @param string $idClaveMaestra Clave maestra que cifró la clave de datos.
     *
     * @return string Contenido en claro.
     *
     * @throws IntegridadDocumentoException si el objeto está alterado
     */
    public function descifrar(string $contenido, string $claveCifrada, string $idClaveMaestra): string
    {
        $claveDatos = $this->claves->descifrarClave($claveCifrada, $idClaveMaestra);

        $claro = strlen($contenido) < self::LONGITUD_CABECERA ? false : openssl_decrypt(
            substr($contenido, self::LONGITUD_CABECERA),
            self::CIFRADO,
            $claveDatos,
            OPENSSL_RAW_DATA,
            substr($contenido, 0, 12),
            substr($contenido, 12, 16)
        );

        sodium_memzero($claveDatos);

        if ($claro === false) {
            throw new IntegridadDocumentoException('El objeto cifrado está alterado o no corresponde a esta clave.');
        }

        return $claro;
    }
}
