<?php

namespace Modules\Documentos\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Documentos\Contracts\EscanerAntivirus;
use Modules\Documentos\Exceptions\IngestaRechazadaException;
use Modules\Documentos\Models\DocumentoVersion;
use Modules\Documentos\Services\Ingesta\EscanerAntivirusFake;
use Modules\Documentos\Services\Ingesta\EscanerClamAv;
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
}
