<?php

namespace Modules\Documentos\Services;

use App\Models\CatalogoSistema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\Documentos\Enums\OrigenDocumento;
use Modules\Documentos\Models\Documento;

/**
 * Abstracción sobre Laravel Filesystem para la custodia de documentos.
 *
 * Centraliza subida, descarga y verificación de integridad. Los ficheros
 * nunca se sirven desde rutas públicas: todo acceso genera URLs temporales.
 *
 * El disco activo se configura en config('documentos.disco'). Cambiar el disco
 * en el entorno no requiere cambios de código.
 */
class ServicioAlmacenamiento
{
    /**
     * Guarda un fichero PDF y retorna el Documento persistido.
     *
     * @param  UploadedFile           $fichero      Fichero recibido del formulario
     * @param  string                 $contexto     Nombre del tipo de documento (clave en catalogos_sistema)
     * @param  int                    $subidoPor    ID del usuario que realiza la subida
     * @param  int                    $tipoDocId    ID del registro en catalogos_sistema
     * @param  object                 $documentable Entidad a la que se asocia el documento
     * @return Documento
     *
     * @throws \InvalidArgumentException si el fichero no es un PDF
     */
    public function guardar(
        UploadedFile $fichero,
        string $contexto,
        int $subidoPor,
        int $tipoDocId,
        object $documentable
    ): Documento {
        if ($fichero->getMimeType() !== 'application/pdf') {
            throw new \InvalidArgumentException(
                "Solo se admiten ficheros PDF. El fichero '{$fichero->getClientOriginalName()}' " .
                "es de tipo '{$fichero->getMimeType()}'."
            );
        }

        $disco   = config('documentos.disco', 'local');
        $anyo    = now()->format('Y');
        $mes     = now()->format('m');
        $uuid    = Str::uuid()->toString();
        $ruta    = "documentos/{$anyo}/{$mes}/{$uuid}.pdf";
        $hash    = hash('sha256', file_get_contents($fichero->getRealPath()));

        Storage::disk($disco)->putFileAs(
            "documentos/{$anyo}/{$mes}",
            $fichero,
            "{$uuid}.pdf"
        );

        return Documento::create([
            'documentable_type'   => get_class($documentable),
            'documentable_id'     => $documentable->getKey(),
            'tipo_documento_id'   => $tipoDocId,
            'origen'              => OrigenDocumento::Externo->value,
            'nombre_original'     => $fichero->getClientOriginalName(),
            'ruta_almacenamiento' => $ruta,
            'disco'               => $disco,
            'mime_type'           => $fichero->getMimeType(),
            'tamano_bytes'        => $fichero->getSize(),
            'hash_sha256'         => $hash,
            'subido_por'          => $subidoPor,
        ]);
    }

    /**
     * Guarda un PDF ya generado internamente (informes firmados).
     *
     * @param  string $contenidoPdf  Contenido binario del PDF
     * @param  int    $subidoPor     ID del usuario
     * @param  int    $tipoDocId     ID del tipo en catalogos_sistema
     * @param  object $documentable  Entidad asociada
     * @param  string $nombreOriginal Nombre de fichero
     * @return Documento
     */
    public function guardarGenerado(
        string $contenidoPdf,
        int $subidoPor,
        int $tipoDocId,
        object $documentable,
        string $nombreOriginal
    ): Documento {
        $disco   = config('documentos.disco', 'local');
        $anyo    = now()->format('Y');
        $mes     = now()->format('m');
        $uuid    = Str::uuid()->toString();
        $ruta    = "documentos/{$anyo}/{$mes}/{$uuid}.pdf";
        $hash    = hash('sha256', $contenidoPdf);

        Storage::disk($disco)->put($ruta, $contenidoPdf);

        return Documento::create([
            'documentable_type'   => get_class($documentable),
            'documentable_id'     => $documentable->getKey(),
            'tipo_documento_id'   => $tipoDocId,
            'origen'              => OrigenDocumento::Generado->value,
            'nombre_original'     => $nombreOriginal,
            'ruta_almacenamiento' => $ruta,
            'disco'               => $disco,
            'mime_type'           => 'application/pdf',
            'tamano_bytes'        => strlen($contenidoPdf),
            'hash_sha256'         => $hash,
            'subido_por'          => $subidoPor,
        ]);
    }

    /**
     * Genera una URL temporal para acceder al documento.
     *
     * En discos que soportan URLs temporales (S3, etc.) usa temporaryUrl().
     * En disco local (desarrollo) genera una URL firmada de Laravel.
     *
     * @param  Documento $documento
     * @param  int       $minutosExpiracion
     * @return string
     */
    public function urlTemporal(Documento $documento, int $minutosExpiracion = 30): string
    {
        $expiracion = now()->addMinutes($minutosExpiracion);
        $disco      = Storage::disk($documento->disco);

        try {
            return $disco->temporaryUrl($documento->ruta_almacenamiento, $expiracion);
        } catch (\RuntimeException) {
            // El disco local no soporta URLs temporales: usamos ruta firmada de Laravel
            return URL::temporarySignedRoute(
                'documentos.ver',
                $expiracion,
                ['documento' => $documento->id]
            );
        }
    }

    /**
     * Verifica la integridad del fichero comparando su hash SHA-256 actual
     * con el calculado en el momento de la subida.
     *
     * @return bool true si el fichero no ha sido alterado
     */
    public function verificarIntegridad(Documento $documento): bool
    {
        $contenido = Storage::disk($documento->disco)->get($documento->ruta_almacenamiento);

        if ($contenido === null) {
            return false;
        }

        return hash('sha256', $contenido) === $documento->hash_sha256;
    }

    /**
     * Elimina el fichero del disco. No elimina el registro en base de datos.
     */
    public function eliminarFichero(Documento $documento): void
    {
        Storage::disk($documento->disco)->delete($documento->ruta_almacenamiento);
    }
}
