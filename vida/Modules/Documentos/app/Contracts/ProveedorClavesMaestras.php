<?php

namespace Modules\Documentos\Contracts;

/**
 * Custodia de la clave maestra que cifra las claves de datos de cada versión (envelope encryption).
 *
 * En esta fase solo hay implementación local. En producción irá un KMS, Vault o HSM:
 * la clave maestra no debe vivir junto a la BBDD ni junto a los ficheros.
 */
interface ProveedorClavesMaestras
{
    /**
     * Cifra una clave de datos con la clave maestra vigente.
     *
     * @param string $claveDatos Clave de datos en binario.
     *
     * @return array{clave_cifrada: string, id_clave_maestra: string}
     */
    public function cifrarClave(string $claveDatos): array;

    /**
     * Descifra una clave de datos.
     *
     * @param string $claveCifrada Clave de datos cifrada (tal como se guardó).
     * @param string $idClaveMaestra Clave maestra que la cifró.
     *
     * @return string Clave de datos en binario.
     */
    public function descifrarClave(string $claveCifrada, string $idClaveMaestra): string;
}
