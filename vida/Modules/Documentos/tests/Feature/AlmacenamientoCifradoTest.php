<?php

namespace Modules\Documentos\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Documentos\Contracts\AlmacenDocumentos;
use Modules\Documentos\Exceptions\ConfiguracionDocumentosException;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\DocumentoVersion;
use Modules\Documentos\Services\Almacenamiento\AlmacenFlysystem;
use Modules\Documentos\Services\Almacenamiento\CifradorDocumentos;
use Modules\Documentos\Tests\Concerns\DocumentosTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo C — Almacenamiento y cifrado (TF-DOC-39 a 45).
 *
 * @see docs/instrucciones-cli/documentos-custodia-tests.md
 */
class AlmacenamientoCifradoTest extends TestCase
{
    use DocumentosTestSetup;
    use RefreshDatabase;

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
     * Contenido cifrado tal como está en el disco para una versión.
     *
     * @param DocumentoVersion $version Versión.
     *
     * @return string
     */
    private function objetoCifrado(DocumentoVersion $version): string
    {
        return Storage::disk('documentos')->get(app(AlmacenFlysystem::class)->ruta($version->clave_almacenamiento));
    }

    #[Test]
    public function tf_doc_39_el_fichero_almacenado_esta_cifrado(): void
    {
        $version = $this->alta([$this->ana])->versionVigente;
        $original = file_get_contents($this->fixture('valido.pdf'));
        $objeto = $this->objetoCifrado($version);

        $this->assertStringStartsNotWith('%PDF', $objeto);
        foreach (['%PDF', '/Type', '/Page', 'endobj', 'stream', '%%EOF'] as $cadena) {
            $this->assertStringContainsString($cadena, $original, "La prueba necesita que el original contenga «{$cadena}».");
            $this->assertStringNotContainsString($cadena, $objeto);
        }
        $this->assertNotSame($version->hash_sha256, hash('sha256', $objeto));
    }

    #[Test]
    public function tf_doc_40_el_fichero_descifrado_coincide_con_el_hash_registrado(): void
    {
        $version = $this->alta([$this->ana])->versionVigente;

        $claro = app(CifradorDocumentos::class)->descifrar(
            app(AlmacenDocumentos::class)->leer($version->clave_almacenamiento),
            $version->clave_cifrada,
            $version->id_clave_maestra,
        );

        $this->assertStringStartsWith('%PDF', $claro);
        // El hash registrado es el del PDF normalizado (saneado a PDF/A), no el del original subido.
        $this->assertSame($version->hash_sha256, hash('sha256', $claro));
    }

    #[Test]
    public function tf_doc_41_cada_version_tiene_su_propia_clave_de_datos(): void
    {
        $a = $this->alta([$this->ana])->versionVigente;
        $b = $this->alta([$this->luis])->versionVigente;

        $this->assertNotSame($a->clave_cifrada, $b->clave_cifrada);
        $this->assertNotSame($this->objetoCifrado($a), $this->objetoCifrado($b));
    }

    #[Test]
    public function tf_doc_42_la_clave_de_almacenamiento_no_contiene_informacion(): void
    {
        $this->ana->forceFill(['nombre' => 'Ana', 'apellido1' => 'Garcia'])->saveQuietly();
        $version = $this->alta([$this->ana], datos: ['nombreOriginal' => 'dni_ana_garcia.pdf'])->versionVigente;

        $objetos = $this->objetosEnDisco();
        $this->assertCount(1, $objetos);
        $ruta = $objetos[0];

        $uuid = $version->clave_almacenamiento;
        $this->assertSame(substr($uuid, 0, 2).'/'.$uuid, $ruta);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{2}\/[0-9a-f-]{36}$/', $ruta);
        foreach (['dni', 'ana', 'garcia', '.pdf'] as $prohibido) {
            $this->assertStringNotContainsStringIgnoringCase($prohibido, $ruta);
        }
        $this->assertStringNotContainsString('/'.$this->ana->id.'/', '/'.$ruta.'/');
    }

    #[Test]
    public function tf_doc_43_el_nombre_original_se_guarda_cifrado_en_bbdd(): void
    {
        $version = $this->alta([$this->ana], datos: ['nombreOriginal' => 'dni_ana_garcia.pdf'])->versionVigente;

        $enBruto = DB::selectOne('select nombre_original from documento_versiones where id = ?', [$version->id])->nombre_original;

        $this->assertStringNotContainsStringIgnoringCase('ana', $enBruto);
        $this->assertStringNotContainsStringIgnoringCase('garcia', $enBruto);
        $this->assertSame('dni_ana_garcia.pdf', DocumentoVersion::findOrFail($version->id)->nombre_original);
    }

    #[Test]
    public function tf_doc_44_sin_clave_maestra_el_servicio_no_funciona(): void
    {
        config(['documentos.clave_maestra' => '']);

        try {
            $this->alta([$this->ana]);
            $this->fail('Sin clave maestra no debe poder ingerirse ningún documento.');
        } catch (ConfiguracionDocumentosException) {
            // esperado
        }

        $this->assertSame([], $this->objetosEnDisco());
        $this->assertSame(0, Documento::count());
    }

    #[Test]
    public function tf_doc_45_la_verificacion_de_integridad_detecta_un_fichero_alterado(): void
    {
        $sana = $this->alta([$this->ana], datos: ['nombreOriginal' => 'informe_ana.pdf'])->versionVigente;
        $alterada = $this->alta([$this->luis], datos: ['nombreOriginal' => 'informe_luis.pdf'])->versionVigente;

        $ruta = app(AlmacenFlysystem::class)->ruta($alterada->clave_almacenamiento);
        $objeto = Storage::disk('documentos')->get($ruta);
        $objeto[40] = chr(ord($objeto[40]) ^ 0xFF);
        Storage::disk('documentos')->put($ruta, $objeto);

        $codigo = Artisan::call('documentos:verificar-integridad');
        $salida = Artisan::output();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString("FALLO  versión #{$alterada->id}", $salida);
        $this->assertStringContainsString("OK     versión #{$sana->id}", $salida);
        foreach (['informe_ana', 'informe_luis', '%PDF', 'Documento de prueba'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $salida);
        }
    }
}
