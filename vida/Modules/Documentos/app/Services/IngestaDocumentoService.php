<?php

namespace Modules\Documentos\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Modules\Documentos\Contracts\AlmacenDocumentos;
use Modules\Documentos\Data\DatosIngesta;
use Modules\Documentos\Enums\EstadoDocumento;
use Modules\Documentos\Enums\EstadoVersion;
use Modules\Documentos\Exceptions\IngestaRechazadaException;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\DocumentoVersion;
use Modules\Documentos\Services\Almacenamiento\CifradorDocumentos;

/**
 * Tubería de entrada de documentos: el único camino por el que un fichero llega al almacén.
 *
 * Si falla cualquier paso se lanza IngestaRechazadaException y no queda nada ni en
 * BBDD ni en disco. El original y los intermedios se destruyen siempre.
 *
 * Fase 2a de la custodia v2: solo admite PDF. Antivirus, conversión de imágenes y
 * ofimática, saneado a PDF/A y recompresión se añaden en la fase 2b
 * (pasos 3 a 5 de documentos-custodia-implementacion.md).
 */
class IngestaDocumentoService
{
    /**
     * Inyecta almacén y cifrador.
     *
     * @param AlmacenDocumentos $almacen Almacén de objetos cifrados.
     * @param CifradorDocumentos $cifrador Cifrado por versión.
     */
    public function __construct(
        private readonly AlmacenDocumentos $almacen,
        private readonly CifradorDocumentos $cifrador,
    ) {}

    /**
     * Ingiere un fichero como versión 1 de un documento nuevo, con sus vínculos.
     *
     * @param UploadedFile|string $origen Fichero subido o ruta local, o contenido binario si $esContenido.
     * @param DatosIngesta $datos Tipo, canal, vínculos y metadatos.
     * @param bool $esContenido true si $origen es el contenido binario y no una ruta.
     *
     * @throws IngestaRechazadaException
     *
     * @return DocumentoVersion
     */
    public function ingerir(UploadedFile|string $origen, DatosIngesta $datos, bool $esContenido = false): DocumentoVersion
    {
        $temporal = $this->copiarATemporal($origen, $esContenido);
        $clave = null;

        try {
            $mime = $this->detectarTipo($temporal);
            $pdf = (string) file_get_contents($temporal);
            $paginas = $this->contarPaginas($temporal);
            $this->comprobarLimites($datos, strlen($pdf), $paginas);

            $hash = hash('sha256', $pdf);
            $cifrado = $this->cifrador->cifrar($pdf);
            $clave = (string) Str::uuid();
            $this->almacen->guardar($clave, $cifrado['contenido']);

            return DB::transaction(fn (): DocumentoVersion => $this->registrar(
                $datos, $clave, $hash, strlen($pdf), $paginas, $mime, $cifrado, $origen,
            ));
        } catch (\Throwable $e) {
            // Si la transacción falló después de escribir, el objeto no debe quedar huérfano.
            if ($clave !== null) {
                $this->almacen->eliminar($clave);
            }

            throw $e;
        } finally {
            @unlink($temporal);
        }
    }

    /**
     * Crea documento, versión y vínculos.
     *
     * @param DatosIngesta $datos Datos del alta.
     * @param string $clave Clave de almacenamiento del objeto ya escrito.
     * @param string $hash SHA-256 del PDF en claro.
     * @param int $bytes Tamaño del PDF en claro.
     * @param int $paginas Número de páginas.
     * @param string $mime MIME detectado en la entrada.
     * @param array{contenido: string, clave_cifrada: string, id_clave_maestra: string} $cifrado Resultado del cifrado.
     * @param UploadedFile|string $origen Fichero de origen, para el nombre original.
     *
     * @return DocumentoVersion
     */
    private function registrar(
        DatosIngesta $datos,
        string $clave,
        string $hash,
        int $bytes,
        int $paginas,
        string $mime,
        array $cifrado,
        UploadedFile|string $origen,
    ): DocumentoVersion {
        $ahora = now();

        $documento = Documento::create([
            'tipo_documental_id' => $datos->tipo->id,
            'titulo' => $datos->titulo,
            'fecha_emision' => $datos->fechaEmision,
            'fecha_validez' => $datos->tipo->fechaValidezDesde($ahora),
            'organo_emisor' => $datos->organoEmisor,
            'visible_ciudadano' => $datos->visibleCiudadano ?? (bool) $datos->tipo->visible_ciudadano_defecto,
            'estado' => EstadoDocumento::Vigente,
            'metadatos' => $datos->metadatos,
            'created_by' => $datos->usuario->id,
        ]);

        $version = $documento->versiones()->create([
            'numero' => 1,
            'clave_almacenamiento' => $clave,
            'disco' => $this->almacen->disco(),
            'hash_sha256' => $hash,
            'tamanyo_bytes' => $bytes,
            'paginas' => $paginas,
            'nombre_original' => $datos->nombreOriginal
                ?? ($origen instanceof UploadedFile ? $origen->getClientOriginalName() : 'documento.pdf'),
            'mime_original' => $mime,
            'convertido' => false,
            'canal' => $datos->canal,
            'subido_por' => $datos->usuario->id,
            'fecha_captura' => $ahora,
            'plantilla_informe_id' => $datos->plantillaInformeId,
            'informe_id' => $datos->informeId,
            'estado' => EstadoVersion::Vigente,
            'clave_cifrada' => $cifrado['clave_cifrada'],
            'id_clave_maestra' => $cifrado['id_clave_maestra'],
        ]);

        foreach ($datos->vinculos as $entidad) {
            $documento->vinculos()->create([
                'vinculable_type' => $entidad->getMorphClass(),
                'vinculable_id' => $entidad->getKey(),
                'fecha_alta' => $ahora,
                'creado_por' => $datos->usuario->id,
            ]);
        }

        return $version;
    }

    /**
     * Copia el origen a la zona temporal de ingesta (nunca al disco de documentos).
     *
     * @param UploadedFile|string $origen Fichero, ruta o contenido.
     * @param bool $esContenido true si $origen es contenido binario.
     *
     * @return string Ruta del temporal.
     *
     * @throws IngestaRechazadaException si no se puede leer el origen
     */
    private function copiarATemporal(UploadedFile|string $origen, bool $esContenido): string
    {
        $directorio = (string) config('documentos.ingesta.directorio_temporal');

        if (! is_dir($directorio)) {
            mkdir($directorio, 0700, true);
        }

        $temporal = $directorio.'/'.Str::uuid();
        $ruta = $origen instanceof UploadedFile ? $origen->getRealPath() : $origen;

        $copiado = $esContenido
            ? file_put_contents($temporal, $origen) !== false
            : (is_string($ruta) && is_file($ruta) && copy($ruta, $temporal));

        if (! $copiado) {
            @unlink($temporal);

            throw new IngestaRechazadaException('fichero_no_legible');
        }

        chmod($temporal, 0600);

        return $temporal;
    }

    /**
     * Detecta el tipo por contenido (magic bytes); la extensión se ignora.
     *
     * @param string $ruta Fichero temporal.
     *
     * @return string MIME detectado.
     *
     * @throws IngestaRechazadaException si no es un formato admitido
     */
    private function detectarTipo(string $ruta): string
    {
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($ruta);

        // Fase 2a: solo PDF. Imágenes y ofimática se admitirán cuando exista la conversión (2b).
        if ($mime !== 'application/pdf') {
            throw new IngestaRechazadaException('formato_no_admitido');
        }

        return $mime;
    }

    /**
     * Número de páginas del PDF (pdfinfo).
     *
     * @param string $ruta PDF temporal.
     *
     * @throws IngestaRechazadaException si el PDF no se puede interpretar
     *
     * @return int
     */
    private function contarPaginas(string $ruta): int
    {
        $resultado = Process::timeout((int) config('documentos.ingesta.timeout_saneado_segundos'))
            ->run([(string) config('documentos.binarios.pdfinfo'), $ruta]);

        if ($resultado->failed() || ! preg_match('/^Pages:\s+(\d+)/m', $resultado->output(), $coincidencia)) {
            throw new IngestaRechazadaException('pdf_no_normalizable');
        }

        return (int) $coincidencia[1];
    }

    /**
     * Aplica los límites de páginas y tamaño del tipo documental.
     *
     * @param DatosIngesta $datos Datos del alta (tipo).
     * @param int $bytes Tamaño del PDF.
     * @param int $paginas Páginas del PDF.
     *
     * @throws IngestaRechazadaException
     *
     * @return void
     */
    private function comprobarLimites(DatosIngesta $datos, int $bytes, int $paginas): void
    {
        if ($paginas > $datos->tipo->max_paginas) {
            throw new IngestaRechazadaException('demasiadas_paginas');
        }

        // La recompresión previa al rechazo llega con el saneado (fase 2b).
        if ($bytes > $datos->tipo->max_bytes) {
            throw new IngestaRechazadaException('tamanyo_excedido');
        }
    }
}
