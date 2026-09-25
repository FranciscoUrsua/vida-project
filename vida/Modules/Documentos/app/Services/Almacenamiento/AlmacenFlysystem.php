<?php

namespace Modules\Documentos\Services\Almacenamiento;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Modules\Documentos\Contracts\AlmacenDocumentos;
use Modules\Documentos\Exceptions\ConfiguracionDocumentosException;

/**
 * Almacén de documentos sobre la abstracción de discos de Laravel (Flysystem).
 *
 * El disco sale de config('documentos.disco'); cambiar a S3 u otro proveedor es
 * cambiar el driver de ese disco, no este código. La ruta de cada objeto es
 * «dos primeros caracteres del UUID / UUID», sin extensión ni nada más.
 */
class AlmacenFlysystem implements AlmacenDocumentos
{
    /** @var array<string, bool> Discos locales cuya raíz ya se comprobó. */
    private array $comprobados = [];

    /**
     * {@inheritDoc}
     */
    public function disco(): string
    {
        return (string) config('documentos.disco');
    }

    /**
     * {@inheritDoc}
     */
    public function guardar(string $clave, string $contenidoCifrado): void
    {
        $this->filesystem()->put($this->ruta($clave), $contenidoCifrado);
    }

    /**
     * {@inheritDoc}
     */
    public function leer(string $clave, ?string $disco = null): string
    {
        return (string) $this->filesystem($disco)->get($this->ruta($clave));
    }

    /**
     * {@inheritDoc}
     */
    public function existe(string $clave, ?string $disco = null): bool
    {
        return $this->filesystem($disco)->exists($this->ruta($clave));
    }

    /**
     * {@inheritDoc}
     */
    public function eliminar(string $clave, ?string $disco = null): void
    {
        $this->filesystem($disco)->delete($this->ruta($clave));
    }

    /**
     * {@inheritDoc}
     */
    public function listar(): array
    {
        $filesystem = $this->filesystem();
        $objetos = [];

        foreach ($filesystem->allFiles() as $ruta) {
            $objetos[basename($ruta)] = $filesystem->lastModified($ruta);
        }

        return $objetos;
    }

    /**
     * Ruta del objeto en el disco: subdirectorio con los dos primeros caracteres y el UUID.
     *
     * @param string $clave Clave de almacenamiento.
     *
     * @return string
     */
    public function ruta(string $clave): string
    {
        return substr($clave, 0, 2).'/'.$clave;
    }

    /**
     * Disco de documentos, comprobando antes que su raíz local existe y se puede escribir.
     *
     * La comprobación va antes de abrir el disco porque el adaptador local de Flysystem
     * crea la raíz al instanciarse, y no debe crearse en ninguna ubicación por sorpresa.
     *
     * @param string|null $disco Disco concreto; null = disco configurado.
     *
     * @throws ConfiguracionDocumentosException si la raíz no existe o no se puede escribir
     *
     * @return Filesystem
     */
    private function filesystem(?string $disco = null): Filesystem
    {
        $disco ??= $this->disco();

        if (! isset($this->comprobados[$disco])) {
            $config = config("filesystems.disks.{$disco}");

            if (! is_array($config)) {
                throw new ConfiguracionDocumentosException("El disco de documentos «{$disco}» no está configurado.");
            }

            $raiz = $config['root'] ?? null;

            if (($config['driver'] ?? null) === 'local' && (! is_string($raiz) || ! is_dir($raiz) || ! is_writable($raiz))) {
                throw new ConfiguracionDocumentosException(
                    "El directorio de documentos no existe o no se puede escribir. Revisa DOCUMENTOS_RUTA ({$raiz})."
                );
            }

            $this->comprobados[$disco] = true;
        }

        return Storage::disk($disco);
    }
}
