<?php

namespace Modules\Documentos\Services\Almacenamiento;

use Modules\Documentos\Contracts\ProveedorClavesMaestras;
use Modules\Documentos\Exceptions\ConfiguracionDocumentosException;
use Modules\Documentos\Exceptions\IntegridadDocumentoException;

/**
 * Proveedor de clave maestra local: la lee de DOCUMENTOS_CLAVE_MAESTRA.
 *
 * Solo para desarrollo, pruebas y la fase actual; en producción se sustituirá por
 * un KMS, Vault o HSM. Cifra las claves de datos con AES-256-GCM.
 */
class ProveedorClavesLocal implements ProveedorClavesMaestras
{
    private const CIFRADO = 'aes-256-gcm';

    private string $claveMaestra;

    private string $idClaveMaestra;

    /**
     * Carga y valida la clave maestra de la configuración.
     *
     * @throws ConfiguracionDocumentosException si falta o no tiene 32 bytes
     */
    public function __construct()
    {
        $configurada = (string) config('documentos.clave_maestra');

        if ($configurada === '') {
            throw new ConfiguracionDocumentosException('Falta DOCUMENTOS_CLAVE_MAESTRA: la custodia de documentos no funciona sin clave maestra.');
        }

        $clave = str_starts_with($configurada, 'base64:')
            ? base64_decode(substr($configurada, 7), true)
            : false;

        if ($clave === false || strlen($clave) !== 32) {
            throw new ConfiguracionDocumentosException('DOCUMENTOS_CLAVE_MAESTRA debe ser «base64:» seguido de 32 bytes en base64.');
        }

        if (hash_equals($configurada, (string) config('app.key'))) {
            throw new ConfiguracionDocumentosException('DOCUMENTOS_CLAVE_MAESTRA no puede ser igual a APP_KEY.');
        }

        $this->claveMaestra = $clave;
        // Identifica la clave sin revelarla, para poder rotarla más adelante.
        $this->idClaveMaestra = 'local-'.substr(hash('sha256', $clave), 0, 16);
    }

    /**
     * {@inheritDoc}
     */
    public function cifrarClave(string $claveDatos): array
    {
        $nonce = random_bytes(12);
        $tag = '';
        $cifrada = openssl_encrypt($claveDatos, self::CIFRADO, $this->claveMaestra, OPENSSL_RAW_DATA, $nonce, $tag);

        return [
            'clave_cifrada' => base64_encode($nonce.$tag.$cifrada),
            'id_clave_maestra' => $this->idClaveMaestra,
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @throws ConfiguracionDocumentosException si la clave la cifró otra clave maestra
     * @throws IntegridadDocumentoException si la clave cifrada está alterada
     */
    public function descifrarClave(string $claveCifrada, string $idClaveMaestra): string
    {
        if ($idClaveMaestra !== $this->idClaveMaestra) {
            throw new ConfiguracionDocumentosException("La clave de datos se cifró con otra clave maestra ({$idClaveMaestra}).");
        }

        $binario = base64_decode($claveCifrada, true);
        $claro = $binario === false || strlen($binario) < 28 ? false : openssl_decrypt(
            substr($binario, 28),
            self::CIFRADO,
            $this->claveMaestra,
            OPENSSL_RAW_DATA,
            substr($binario, 0, 12),
            substr($binario, 12, 16)
        );

        if ($claro === false) {
            throw new IntegridadDocumentoException('No se ha podido descifrar la clave de datos.');
        }

        return $claro;
    }
}
