<?php

namespace Modules\Documentos\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Modules\Documentos\Contracts\AlmacenDocumentos;
use Modules\Documentos\Contracts\ConversorPdf;
use Modules\Documentos\Contracts\EscanerAntivirus;
use Modules\Documentos\Data\DatosIngesta;
use Modules\Documentos\Enums\CanalCaptura;
use Modules\Documentos\Enums\EstadoDocumento;
use Modules\Documentos\Enums\EstadoVersion;
use Modules\Documentos\Exceptions\AntivirusNoDisponibleException;
use Modules\Documentos\Exceptions\IngestaRechazadaException;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\DocumentoVersion;
use Modules\Documentos\Services\Almacenamiento\CifradorDocumentos;
use Modules\Documentos\Services\Ingesta\DetectorFormato;
use Modules\Documentos\Services\Ingesta\SaneadorPdf;

/**
 * Tubería de entrada de documentos: el único camino por el que un fichero llega al almacén.
 *
 * Si falla cualquier paso se lanza IngestaRechazadaException y no queda nada ni en
 * BBDD ni en disco. El original y los intermedios se destruyen siempre.
 *
 * Pasos, en este orden (paso 4 de documentos-custodia-implementacion.md): zona
 * temporal, detección por contenido, antivirus, conversión a PDF, saneado a PDF/A,
 * límites del tipo (con un intento de recompresión), hash, cifrado, almacenamiento
 * y registro en una transacción.
 *
 * Los PDF firmados (canal «generado») no se convierten, sanean ni recomprimen:
 * reescribirlos con Ghostscript invalida la firma PAdES. Solo se comprueba que no
 * estén protegidos ni contengan JavaScript, adjuntos o acciones de lanzamiento.
 */
class IngestaDocumentoService
{
    /**
     * Inyecta almacén, cifrador y las herramientas de la tubería.
     *
     * @param AlmacenDocumentos $almacen Almacén de objetos cifrados.
     * @param CifradorDocumentos $cifrador Cifrado por versión.
     * @param DetectorFormato $detector Detección del formato por contenido.
     * @param EscanerAntivirus $antivirus Escáner antivirus.
     * @param ConversorPdf $conversor Conversión de imágenes y ofimática a PDF.
     * @param SaneadorPdf $saneador Saneado y normalización a PDF/A.
     */
    public function __construct(
        private readonly AlmacenDocumentos $almacen,
        private readonly CifradorDocumentos $cifrador,
        private readonly DetectorFormato $detector,
        private readonly EscanerAntivirus $antivirus,
        private readonly ConversorPdf $conversor,
        private readonly SaneadorPdf $saneador,
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
        $directorio = $this->crearDirectorioTrabajo();
        $clave = null;

        try {
            $original = $this->copiarATemporal($origen, $esContenido, $directorio);
            $mime = $this->detector->detectar($original);
            $this->analizarAntivirus($original);

            $firmado = $datos->canal === CanalCaptura::Generado;
            if ($firmado && $mime !== DetectorFormato::PDF) {
                throw new IngestaRechazadaException('formato_no_admitido');
            }

            $convertido = $mime !== DetectorFormato::PDF;
            $pdf = $convertido ? $this->conversor->convertir($original, $mime, $directorio) : $original;
            $pdf = $this->normalizar($pdf, $directorio, $firmado);

            $paginas = $this->saneador->contarPaginas($pdf);
            if ($paginas > $datos->tipo->max_paginas) {
                throw new IngestaRechazadaException('demasiadas_paginas');
            }
            $pdf = $this->ajustarTamanyo($pdf, $directorio, $datos, $firmado);

            $contenido = (string) file_get_contents($pdf);
            $hash = hash('sha256', $contenido);
            $cifrado = $this->cifrador->cifrar($contenido);
            unset($contenido);
            $clave = (string) Str::uuid();
            $this->almacen->guardar($clave, $cifrado['contenido']);

            return DB::transaction(fn (): DocumentoVersion => $this->registrar(
                $datos, $clave, $hash, (int) filesize($pdf), $paginas, $mime, $convertido, $cifrado, $origen,
            ));
        } catch (\Throwable $e) {
            // Si la transacción falló después de escribir, el objeto no debe quedar huérfano.
            if ($clave !== null) {
                $this->almacen->eliminar($clave);
            }

            throw $e;
        } finally {
            // Destruye el original y todos los intermedios: no se conserva copia en ningún sitio.
            File::deleteDirectory($directorio);
        }
    }

    /**
     * Analiza el original con el antivirus. Un error del escáner es un rechazo.
     *
     * @param string $ruta Fichero original en la zona temporal.
     *
     * @throws IngestaRechazadaException virus_detectado o antivirus_no_disponible
     *
     * @return void
     */
    private function analizarAntivirus(string $ruta): void
    {
        try {
            $firma = $this->antivirus->escanear($ruta);
        } catch (AntivirusNoDisponibleException $e) {
            throw new IngestaRechazadaException('antivirus_no_disponible', $e);
        }

        if ($firma !== null) {
            throw new IngestaRechazadaException('virus_detectado');
        }
    }

    /**
     * Rechaza PDF protegidos y reescribe a PDF/A sin contenido activo.
     *
     * @param string $pdf PDF original o convertido.
     * @param string $directorio Directorio de trabajo.
     * @param bool $firmado true para PDF firmados, que no se reescriben.
     *
     * @throws IngestaRechazadaException
     *
     * @return string Ruta del PDF que sigue por la tubería.
     */
    private function normalizar(string $pdf, string $directorio, bool $firmado): string
    {
        $this->saneador->rechazarSiProtegido($pdf);

        if (! $firmado) {
            $saneado = $directorio.'/saneado.pdf';
            $this->saneador->sanear($pdf, $saneado);
            $pdf = $saneado;
        }

        // En los firmados es la única defensa; en el resto, confirma que el saneado ha funcionado.
        $this->saneador->verificarSinContenidoActivo($pdf, $directorio);

        return $pdf;
    }

    /**
     * Aplica el máximo de bytes del tipo, con un único intento de recompresión.
     *
     * @param string $pdf PDF normalizado.
     * @param string $directorio Directorio de trabajo.
     * @param DatosIngesta $datos Datos del alta (tipo).
     * @param bool $firmado true para PDF firmados, que no se recomprimen.
     *
     * @throws IngestaRechazadaException tamanyo_excedido
     *
     * @return string Ruta del PDF definitivo.
     */
    private function ajustarTamanyo(string $pdf, string $directorio, DatosIngesta $datos, bool $firmado): string
    {
        if (filesize($pdf) <= $datos->tipo->max_bytes) {
            return $pdf;
        }

        if ($firmado) {
            throw new IngestaRechazadaException('tamanyo_excedido');
        }

        $recomprimido = $directorio.'/recomprimido.pdf';
        $this->saneador->recomprimir($pdf, $recomprimido);

        if (filesize($recomprimido) > $datos->tipo->max_bytes) {
            throw new IngestaRechazadaException('tamanyo_excedido');
        }

        return $recomprimido;
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
     * @param bool $convertido true si la entrada no era PDF y se convirtió.
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
        bool $convertido,
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
            'convertido' => $convertido,
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
     * Crea el directorio de trabajo de esta ingesta dentro de la zona temporal.
     *
     * @return string
     */
    private function crearDirectorioTrabajo(): string
    {
        $directorio = rtrim((string) config('documentos.ingesta.directorio_temporal'), '/').'/'.Str::uuid();
        mkdir($directorio, 0700, true);

        return $directorio;
    }

    /**
     * Copia el origen al directorio de trabajo (nunca al disco de documentos).
     *
     * @param UploadedFile|string $origen Fichero, ruta o contenido.
     * @param bool $esContenido true si $origen es contenido binario.
     * @param string $directorio Directorio de trabajo.
     *
     * @throws IngestaRechazadaException si no se puede leer el origen
     *
     * @return string Ruta del original en la zona temporal.
     */
    private function copiarATemporal(UploadedFile|string $origen, bool $esContenido, string $directorio): string
    {
        // Nombre neutro: el nombre original solo se guarda cifrado en la BBDD.
        $temporal = $directorio.'/original';
        $ruta = $origen instanceof UploadedFile ? $origen->getRealPath() : $origen;

        $copiado = $esContenido
            ? file_put_contents($temporal, $origen) !== false
            : (is_string($ruta) && is_file($ruta) && copy($ruta, $temporal));

        if (! $copiado) {
            throw new IngestaRechazadaException('fichero_no_legible');
        }

        chmod($temporal, 0600);

        return $temporal;
    }
}
