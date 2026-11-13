<?php

namespace Modules\Documentos\Contracts;

/**
 * Almacén de los objetos cifrados de documentos.
 *
 * Es el único punto del código que accede al disco de documentos. Solo conoce
 * claves opacas (UUID) y contenido ya cifrado: no sabe qué documento es ni de quién.
 */
interface AlmacenDocumentos
{
    /**
     * Nombre del disco donde se escriben los objetos nuevos.
     *
     * @return string
     */
    public function disco(): string;

    /**
     * Guarda un objeto cifrado.
     *
     * @param string $clave Clave de almacenamiento (UUID).
     * @param string $contenidoCifrado Contenido ya cifrado.
     *
     * @return void
     */
    public function guardar(string $clave, string $contenidoCifrado): void;

    /**
     * Lee un objeto cifrado.
     *
     * @param string $clave Clave de almacenamiento.
     * @param string|null $disco Disco donde se guardó; null = disco actual.
     *
     * @return string
     */
    public function leer(string $clave, ?string $disco = null): string;

    /**
     * Indica si existe el objeto.
     *
     * @param string $clave Clave de almacenamiento.
     * @param string|null $disco Disco donde se guardó; null = disco actual.
     *
     * @return bool
     */
    public function existe(string $clave, ?string $disco = null): bool;

    /**
     * Elimina el objeto si existe.
     *
     * @param string $clave Clave de almacenamiento.
     * @param string|null $disco Disco donde se guardó; null = disco actual.
     *
     * @return void
     */
    public function eliminar(string $clave, ?string $disco = null): void;

    /**
     * Objetos del disco actual con su fecha de última modificación.
     *
     * @return array<string, int> Clave de almacenamiento => timestamp.
     */
    public function listar(): array;
}
