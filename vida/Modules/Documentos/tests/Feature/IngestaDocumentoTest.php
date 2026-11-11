<?php

namespace Modules\Documentos\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Documentos\Contracts\EscanerAntivirus;
use Modules\Documentos\Data\DatosIngesta;
use Modules\Documentos\Enums\CanalCaptura;
use Modules\Documentos\Exceptions\FalloHerramientaExterna;
use Modules\Documentos\Exceptions\IngestaRechazadaException;
use Modules\Documentos\Models\DocumentoVersion;
use Modules\Documentos\Services\Ingesta\EscanerAntivirusFake;
use Modules\Documentos\Services\Ingesta\EscanerClamAv;
use Modules\Documentos\Services\Ingesta\SaneadorPdf;
use Modules\Documentos\Services\IngestaDocumentoService;
use Modules\Documentos\Services\LecturaDocumentoService;
use Modules\Documentos\Tests\Concerns\DocumentosTestSetup;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo D — Tubería de entrada (TF-DOC-46 a 58).
 *
 * La tubería depende siempre de pdfinfo, qpdf y Ghostscript. Los tests [binarios]
 * dependen además de clamd, Imagick o LibreOffice y se omiten si faltan.
 *
 * @see docs/instrucciones-cli/documentos-custodia-tests.md
 */
class IngestaDocumentoTest extends TestCase
{
    use DocumentosTestSetup;
    use RefreshDatabase;

    /** Cadena de prueba EICAR, partida para que el propio fichero de test no se detecte. */
    private const EICAR = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$'.'EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

    /**
     * Prepara actores y tipos comunes, autenticado como profesional.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararDocumentos();
        $this->actingAs($this->profesional);
    }

    /**
     * El escáner simulado que usa la tubería en los tests.
     *
     * @return EscanerAntivirusFake
     */
    private function antivirus(): EscanerAntivirusFake
    {
        $escaner = app(EscanerAntivirus::class);
        $this->assertInstanceOf(EscanerAntivirusFake::class, $escaner);

        return $escaner;
    }

    /**
     * PDF en claro de una versión, escrito en un fichero temporal para inspeccionarlo con qpdf/pdfinfo.
     *
     * @param DocumentoVersion $version Versión custodiada.
     *
     * @return string Ruta del fichero (se borra al terminar el test).
     */
    private function pdfEnClaro(DocumentoVersion $version): string
    {
        $ruta = storage_path('framework/testing/claro-'.Str::uuid().'.pdf');
        file_put_contents($ruta, app(LecturaDocumentoService::class)->contenido($version));
        $this->beforeApplicationDestroyed(fn () => @unlink($ruta));

        return $ruta;
    }

    /**
     * Omite el test si falta un binario del sistema.
     *
     * @param string $binario Nombre del ejecutable.
     *
     * @return void
     */
    private function requiereBinario(string $binario): void
    {
        exec('command -v '.escapeshellarg($binario), $salida, $codigo);
        if ($codigo !== 0) {
            $this->markTestSkipped("Falta el binario «{$binario}» en este entorno.");
        }
    }

    #[Test]
    public function tf_doc_46_un_pdf_valido_se_ingiere_correctamente(): void
    {
        $documento = $this->alta([$this->ana], $this->tipoDni, 'valido.pdf');
        $version = $documento->versionVigente;

        $this->assertSame(1, $version->numero);
        $this->assertSame('vigente', $version->estado->value);
        $this->assertSame(2, $version->paginas);
        $this->assertFalse($version->convertido);
        $this->assertSame('application/pdf', $version->mime_original);
        $this->assertCount(1, $this->antivirus()->analizados, 'El original debe pasar por el antivirus.');
        $this->assertTemporalVacio();
    }

    #[Test]
    public function tf_doc_47_el_tipo_se_detecta_por_contenido_no_por_extension(): void
    {
        $version = $this->alta([$this->ana], fichero: 'pdf-disfrazado.jpg')->versionVigente;
        $this->assertSame('application/pdf', $version->mime_original);
        $this->assertFalse($version->convertido);

        $this->assertIngestaRechazada('zip-disfrazado.pdf', 'formato_no_admitido');
    }

    #[Test]
    public function tf_doc_48_formatos_no_admitidos_se_rechazan(): void
    {
        $this->assertIngestaRechazada('comprimido.zip', 'formato_no_admitido');
        $this->assertIngestaRechazada('ejecutable.exe', 'formato_no_admitido');
        // Un .docm es, por contenido, un DOCX con proyecto VBA.
        $this->assertIngestaRechazada('con-macros.docm', 'macros_no_admitidas');
    }

    #[Test]
    public function tf_doc_49_un_fichero_con_virus_se_rechaza(): void
    {
        $this->antivirus()->infectado();

        $this->assertIngestaRechazada('valido.pdf', 'virus_detectado');
    }

    #[Test]
    public function tf_doc_50_si_el_antivirus_no_responde_se_rechaza(): void
    {
        $this->antivirus()->fallando();

        $this->assertIngestaRechazada('valido.pdf', 'antivirus_no_disponible');
    }

    #[Test]
    #[Group('binarios')]
    public function tf_doc_51_clamav_real_detecta_la_firma_eicar(): void
    {
        $socket = (string) config('documentos.binarios.clamd_socket');
        if (! file_exists($socket)) {
            $this->markTestSkipped("clamd no está disponible ({$socket}).");
        }

        $clamav = new EscanerClamAv;
        $this->app->instance(EscanerAntivirus::class, $clamav);

        $eicar = storage_path('framework/testing/eicar-'.Str::uuid().'.pdf');
        file_put_contents($eicar, self::EICAR);
        $this->beforeApplicationDestroyed(fn () => @unlink($eicar));

        // El escáner real reconoce la firma…
        $this->assertNotNull($clamav->escanear($eicar));
        $this->assertNull($clamav->escanear($this->fixture('valido.pdf')));

        // …y la tubería no deja entrar el fichero, sea cual sea el paso que lo pare.
        $antes = $this->rastroIngesta();
        try {
            $this->alta([$this->ana], fichero: $eicar);
            $this->fail('La firma EICAR no debe entrar en la custodia.');
        } catch (IngestaRechazadaException $e) {
            $this->assertContains($e->codigo, ['virus_detectado', 'formato_no_admitido']);
        }
        $this->assertIngestaSinRastro($antes);
    }

    #[Test]
    #[Group('binarios')]
    public function tf_doc_52_una_imagen_se_convierte_a_pdf_y_el_original_no_se_conserva(): void
    {
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('Falta la extensión imagick.');
        }

        $version = $this->alta([$this->ana], fichero: 'foto.jpg')->versionVigente;

        $this->assertTrue($version->convertido);
        $this->assertSame('image/jpeg', $version->mime_original);
        $this->assertSame(1, $version->paginas);

        $claro = app(LecturaDocumentoService::class)->contenido($version);
        $this->assertStringStartsWith('%PDF', $claro);
        $this->assertStringContainsString('Pages:           1', (string) shell_exec('pdfinfo '.escapeshellarg($this->pdfEnClaro($version))));

        // En el disco solo hay el objeto cifrado de la versión; ni rastro del JPEG.
        $this->assertSame([substr($version->clave_almacenamiento, 0, 2).'/'.$version->clave_almacenamiento], $this->objetosEnDisco());
        foreach ($this->objetosEnDisco() as $objeto) {
            $this->assertStringStartsNotWith("\xFF\xD8\xFF", Storage::disk('documentos')->get($objeto));
        }
        $this->assertTemporalVacio();
    }

    #[Test]
    #[Group('binarios')]
    public function tf_doc_53_un_docx_se_convierte_a_pdf(): void
    {
        $this->requiereBinario((string) config('documentos.binarios.soffice'));

        $version = $this->alta([$this->ana], fichero: 'documento.docx')->versionVigente;

        $this->assertTrue($version->convertido);
        $this->assertSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $version->mime_original);
        $this->assertStringStartsWith('%PDF', app(LecturaDocumentoService::class)->contenido($version));
        $this->assertStringContainsString('Documento de prueba VIDA', (string) shell_exec('pdftotext '.escapeshellarg($this->pdfEnClaro($version)).' -'));

        $this->assertCount(1, $this->objetosEnDisco());
        $this->assertTemporalVacio();
    }

    #[Test]
    public function tf_doc_54_un_pdf_protegido_con_contrasena_se_rechaza(): void
    {
        $this->assertIngestaRechazada('protegido.pdf', 'pdf_protegido');
    }

    #[Test]
    #[Group('binarios')]
    public function tf_doc_55_el_saneado_elimina_javascript_y_adjuntos(): void
    {
        foreach (['con-javascript.pdf', 'con-adjunto.pdf'] as $fixture) {
            // La prueba solo vale si el original contiene lo que hay que eliminar.
            $original = (string) shell_exec('qpdf --qdf --object-streams=disable '.escapeshellarg($this->fixture($fixture)).' - 2>/dev/null');
            $this->assertMatchesRegularExpression('#/(JavaScript|EmbeddedFile)#', $original, "El fixture {$fixture} no tiene contenido activo.");

            $version = $this->alta([$this->ana], fichero: $fixture)->versionVigente;
            $expandido = (string) shell_exec('qpdf --qdf --object-streams=disable '.escapeshellarg($this->pdfEnClaro($version)).' - 2>/dev/null');

            $this->assertStringStartsWith('%PDF', $expandido);
            foreach (['/JavaScript', '/JS ', '/JS(', '/EmbeddedFile', '/Launch'] as $prohibido) {
                $this->assertStringNotContainsString($prohibido, $expandido, "{$fixture} conserva {$prohibido} tras el saneado.");
            }
        }
    }

    #[Test]
    public function tf_doc_56_se_rechaza_un_pdf_que_excede_el_maximo_de_paginas_del_tipo(): void
    {
        $this->tipoDni->update(['max_paginas' => 50]);
        $this->assertIngestaRechazada('largo.pdf', 'demasiadas_paginas', $this->tipoDni);

        // Negativo: con un límite suficiente, el mismo fichero entra.
        $amplio = $this->tipo('dni_amplio', ['max_paginas' => 100]);
        $this->assertSame(60, $this->alta([$this->ana], $amplio, 'largo.pdf')->versionVigente->paginas);
    }

    #[Test]
    public function tf_doc_57_se_rechaza_un_fichero_que_excede_el_tamanyo_maximo_tras_recompresion(): void
    {
        $diminuto = $this->tipo('diminuto', ['max_bytes' => 512]);
        $this->assertIngestaRechazada('valido.pdf', 'tamanyo_excedido', $diminuto);

        // Negativo: con el límite por defecto entra.
        $this->assertNotNull($this->alta([$this->ana], fichero: 'valido.pdf')->versionVigente);
    }

    #[Test]
    public function tf_doc_58_si_falla_la_transaccion_no_queda_el_objeto_en_disco(): void
    {
        $antes = $this->rastroIngesta();
        DocumentoVersion::creating(function (): void {
            throw new \RuntimeException('Fallo forzado al insertar la versión.');
        });

        try {
            $this->alta([$this->ana]);
            $this->fail('La excepción de la transacción debe propagarse.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Fallo forzado al insertar la versión.', $e->getMessage());
        }

        $this->assertIngestaSinRastro($antes);
    }

    // ── Rechazos que faltaban (TF-DOC-ING-02 a 07, instrucciones 2026_11_estabilizacion_y_privacidad A1) ──

    #[Test]
    public function tf_doc_ing_02_un_fichero_de_0_bytes_se_rechaza_en_el_servicio(): void
    {
        $antes = $this->rastroIngesta();
        $datos = new DatosIngesta(
            tipo: $this->tipoDni,
            usuario: $this->profesional,
            canal: CanalCaptura::Presencial,
            vinculos: [$this->ana],
        );

        try {
            app(IngestaDocumentoService::class)->ingerir('', $datos, esContenido: true);
            $this->fail('Un fichero vacío debe rechazarse.');
        } catch (IngestaRechazadaException $e) {
            $this->assertSame('fichero_vacio', $e->codigo);
            $this->assertSame('El fichero está vacío.', $e->getMessage());
        }

        $this->assertIngestaSinRastro($antes);
        $this->assertSame([], $this->antivirus()->analizados, 'Un fichero vacío no llega al antivirus.');
    }

    #[Test]
    public function tf_doc_ing_03_un_pdf_danado_que_ghostscript_repara_en_blanco_se_rechaza(): void
    {
        // Flujos de contenido dañados y referencias intactas: qpdf lo abre sin avisos y
        // Ghostscript lo «repara» con código 0, pero el resultado no tiene nada visible.
        $this->assertIngestaRechazada('contenido-danado.pdf', 'pdf_sin_contenido');

        // Un PDF truncado sin trailer no llega a Ghostscript: qpdf no puede abrirlo.
        $this->assertIngestaRechazada('truncado.pdf', 'pdf_no_normalizable');
    }

    #[Test]
    public function tf_doc_ing_04_un_pdf_de_una_pagina_en_blanco_se_rechaza(): void
    {
        $this->assertIngestaRechazada('en-blanco.pdf', 'pdf_sin_contenido');
    }

    #[Test]
    public function tf_doc_ing_05_una_pagina_en_blanco_entre_otras_con_contenido_se_admite(): void
    {
        $version = $this->alta([$this->ana], fichero: 'tres-paginas-segunda-en-blanco.pdf')->versionVigente;

        $this->assertSame(3, $version->paginas);
    }

    #[Test]
    #[Group('binarios')]
    public function tf_doc_ing_06_la_imagen_jpeg_de_un_documento_se_admite(): void
    {
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('Falta la extensión imagick.');
        }

        $version = $this->alta([$this->ana], fichero: 'foto.jpg')->versionVigente;

        $this->assertTrue($version->convertido);
        $this->assertSame('image/jpeg', $version->mime_original);
    }

    #[Test]
    public function tf_doc_ing_07_qpdf_con_codigo_2_y_salida_de_error_no_se_toma_como_sin_cifrar(): void
    {
        $binario = $this->binarioFalso('qpdf', "echo 'qpdf: open /tmp/x.pdf: Permission denied' >&2\nexit 2");
        config(['documentos.binarios.qpdf' => $binario]);

        try {
            app(SaneadorPdf::class)->rechazarSiProtegido($this->fixture('valido.pdf'));
            $this->fail('Con salida de error, el código 2 de qpdf no es «sin cifrar».');
        } catch (IngestaRechazadaException $e) {
            $this->assertSame('pdf_no_normalizable', $e->codigo);
            $causa = $e->getPrevious();
            $this->assertInstanceOf(FalloHerramientaExterna::class, $causa);
            $this->assertSame(2, $causa->codigoSalida);
            $this->assertSame(basename($binario), $causa->comando[0], 'Del binario solo se guarda el nombre.');
            $this->assertContains('--requires-password', $causa->comando);
            $this->assertStringContainsString('Permission denied', $causa->salidaError);
        }

        // Sin salida de error, el mismo código 2 sí significa «sin cifrar».
        config(['documentos.binarios.qpdf' => $this->binarioFalso('qpdf', 'exit 2')]);
        app(SaneadorPdf::class)->rechazarSiProtegido($this->fixture('valido.pdf'));
    }

    #[Test]
    public function la_causa_tecnica_conserva_solo_los_primeros_2000_caracteres_de_la_salida_de_error(): void
    {
        $causa = new FalloHerramientaExterna(['/usr/bin/gs', '-q'], 1, str_repeat('x', 5000), 'Prueba');

        $this->assertSame(FalloHerramientaExterna::MAX_SALIDA_ERROR, mb_strlen($causa->salidaError));
        $this->assertSame(['gs', '-q'], $causa->comando);
    }

    /**
     * Script ejecutable que sustituye a un binario externo, borrado al terminar el test.
     *
     * @param string $nombre Prefijo del nombre del script.
     * @param string $cuerpo Órdenes de shell (sin la línea #!).
     *
     * @return string Ruta del script.
     */
    private function binarioFalso(string $nombre, string $cuerpo): string
    {
        $ruta = storage_path('framework/testing/'.$nombre.'-falso-'.Str::random(8).'.sh');
        file_put_contents($ruta, "#!/bin/sh\n{$cuerpo}\n");
        chmod($ruta, 0755);
        $this->beforeApplicationDestroyed(fn () => @unlink($ruta));

        return $ruta;
    }
}
