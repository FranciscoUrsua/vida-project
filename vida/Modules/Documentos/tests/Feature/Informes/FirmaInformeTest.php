<?php

namespace Modules\Documentos\Tests\Feature\Informes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Process;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Documentos\Enums\EstadoDocumento;
use Modules\Documentos\Enums\EstadoInforme;
use Modules\Documentos\Enums\MetodoFirma;
use Modules\Documentos\Exceptions\FalloHerramientaExterna;
use Modules\Documentos\Exceptions\FirmaRechazadaException;
use Modules\Documentos\Http\Livewire\InformesHistorial;
use Modules\Documentos\Http\Livewire\NuevoInformeWizard;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\Informe;
use Modules\Documentos\Services\LecturaDocumentoService;
use Modules\Documentos\Services\ServicioFirmaInforme;
use Modules\Documentos\Services\ServicioGeneracionPDF;
use Modules\Documentos\Tests\Concerns\InformesTestSetup;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Models\Apunte;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo C — Firma (TF-INF-11, 12, 15, 16, 17, 18, 19 y 20). TF-INF-13 y 14, en
 * ValidadorFirmaPdfTest.
 */
class FirmaInformeTest extends TestCase
{
    use InformesTestSetup;
    use RefreshDatabase;

    /**
     * Prepara el escenario de informes.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarInformes();
    }

    #[Test]
    public function tf_inf_11_el_autor_descarga_firma_y_sube_el_pdf(): void
    {
        $informe = $this->borrador($this->tsNorte);

        $asistente = $this->enPasoDeFirma($informe)->assertSee('Descargar PDF para firmar');

        $paraFirmar = $this->actingAs($this->tsNorte)->get(route('documentos.informes.para-firmar', $informe))->assertOk()->getContent();
        $firmado = $this->firmador->firmar($paraFirmar);

        $asistente->set('pdfFirmado', $this->subida($firmado))
            ->call('subirFirmado')
            ->assertSet('errorFirma', null)
            ->assertRedirect(route('intervencion.ciudadano.show', ['historia' => $this->historiaAna->id, 'herramienta' => 'informes']));

        $informe->refresh();
        $this->assertSame(EstadoInforme::Firmado, $informe->estado);
        $this->assertSame(MetodoFirma::PdfFirmadoExternamente, $informe->metodo_firma);
        $this->assertSame($this->firmador->titular, $informe->firmante_certificado);
        $this->assertNotNull($informe->firmado_en);

        $documento = Documento::findOrFail($informe->documento_id);
        $this->assertSame('informe_profesional', $documento->tipo->codigo);
        $this->assertTrue(Documento::vinculadosA($this->ana)->whereKey($documento->id)->exists(), 'En la carpeta de Ana');

        $apunte = Apunte::withoutGlobalScopes()->where('apuntable_type', $informe->getMorphClass())->where('apuntable_id', $informe->id)->sole();
        $this->assertSame($this->historiaAna->id, $apunte->historia_id);
        $this->assertSame(TipoApunte::Documento, $apunte->tipo);
        $this->assertStringContainsString($informe->identificador(), (string) $apunte->contenido);
    }

    #[Test]
    public function tf_inf_12_un_pdf_sin_firma_se_rechaza_y_el_informe_sigue_en_borrador(): void
    {
        $informe = $this->borrador($this->tsNorte);
        $sinFirma = app(ServicioGeneracionPDF::class)->generarParaFirmar($informe);

        $this->enPasoDeFirma($informe)
            ->set('pdfFirmado', $this->subida($sinFirma))
            ->call('subirFirmado')
            ->assertSet('errorFirma', 'El PDF no está firmado. Fírmalo con tu certificado y vuelve a subirlo.')
            ->assertSee('El PDF no está firmado.')
            ->assertNoRedirect();

        $this->assertTrue($informe->fresh()->esBorrador());
        $this->assertNull($informe->fresh()->documento_id);
        $this->assertSame(0, Documento::count());
    }

    #[Test]
    public function tf_inf_15_el_pdf_firmado_custodiado_conserva_la_firma_integra(): void
    {
        $informe = $this->borrador($this->tsNorte);
        $firmado = $this->pdfFirmadoDe($informe);

        $this->enPasoDeFirma($informe)->set('pdfFirmado', $this->subida($firmado))->call('subirFirmado');

        $version = Documento::findOrFail($informe->fresh()->documento_id)->versionVigente;
        $guardado = app(LecturaDocumentoService::class)->contenido($version);

        $this->assertSame(hash('sha256', $firmado), hash('sha256', $guardado), 'El PDF firmado no se reescribe (sin PDF/A)');

        $ruta = tempnam(sys_get_temp_dir(), 'vida-custodiado');
        file_put_contents($ruta, $guardado);
        $salida = (string) shell_exec('LC_ALL=C pdfsig '.escapeshellarg($ruta).' 2>/dev/null');
        unlink($ruta);
        $this->assertStringContainsString('Signature is Valid.', $salida);
    }

    #[Test]
    public function tf_inf_16_solo_el_autor_firma(): void
    {
        $informe = $this->borrador($this->tsNorte);

        Livewire::actingAs($this->tsNorte2)
            ->test(NuevoInformeWizard::class, ['informe' => $informe])
            ->assertForbidden();

        $this->actingAs($this->tsNorte2)->get(route('documentos.informes.para-firmar', $informe))->assertForbidden();

        $this->expectException(\DomainException::class);
        app(ServicioFirmaInforme::class)->firmar($informe, MetodoFirma::PdfFirmadoExternamente, $this->pdfFirmadoDe($informe), $this->tsNorte2);
    }

    #[Test]
    public function tf_inf_17_solo_se_firman_borradores(): void
    {
        $firmado = $this->firmado($this->tsNorte);

        Livewire::actingAs($this->tsNorte)
            ->test(NuevoInformeWizard::class, ['informe' => $firmado])
            ->assertForbidden();

        try {
            app(ServicioFirmaInforme::class)->firmar($firmado, MetodoFirma::PdfFirmadoExternamente, 'x', $this->tsNorte);
            $this->fail('Un firmado no se vuelve a firmar');
        } catch (\DomainException) {
        }

        $anulado = app(ServicioFirmaInforme::class)->anular($firmado, $this->tsNorte->id, 'Error en los datos de la persona');
        $this->expectException(\DomainException::class);
        app(ServicioFirmaInforme::class)->firmar($anulado, MetodoFirma::PdfFirmadoExternamente, 'x', $this->tsNorte);
    }

    #[Test]
    public function tf_inf_18_con_pdfsig_fallando_mensaje_generico_y_causa_en_el_log(): void
    {
        Exceptions::fake();
        $informe = $this->borrador($this->tsNorte);
        $firmado = $this->pdfFirmadoDe($informe);

        Process::fake([
            '*pdfsig*' => Process::result(output: '', errorOutput: 'pdfsig: Permission denied', exitCode: 1),
            '*' => Process::result(output: "Pages:          1\n"),
        ]);

        $this->enPasoDeFirma($informe)
            ->set('pdfFirmado', $this->subida($firmado))
            ->call('subirFirmado')
            ->assertSet('errorFirma', 'No se ha podido comprobar la firma del PDF. Inténtalo de nuevo más tarde.')
            ->assertDontSee('Permission denied');

        Exceptions::assertReported(fn (FalloHerramientaExterna $e): bool => str_contains($e->getMessage(), 'Permission denied'));
        $this->assertTrue($informe->fresh()->esBorrador());
    }

    #[Test]
    public function tf_inf_19_autofirma_inactiva_no_se_ofrece_y_rechaza_y_activa_completa_la_firma(): void
    {
        config(['documentos.firma.autofirma_activa' => false]);
        $informe = $this->borrador($this->tsNorte);
        $base64 = base64_encode($this->pdfFirmadoDe($informe));

        $this->enPasoDeFirma($informe)
            ->assertDontSee('Firmar con AutoFirma')
            ->call('firmarConAutoFirma', $base64)
            ->assertForbidden();
        $this->assertTrue($informe->fresh()->esBorrador());

        // El servicio también lo rechaza aunque se llame directamente
        try {
            app(ServicioFirmaInforme::class)->firmar($informe, MetodoFirma::AutofirmaCertificadoEmpleadoPublico, base64_decode($base64), $this->tsNorte);
            $this->fail('Con AutoFirma inactiva no se firma');
        } catch (FirmaRechazadaException $e) {
            $this->assertSame('metodo_inactivo', $e->codigo);
        }

        // Activa: el FirmadorSimulado hace de AutoFirma y devuelve el PDF firmado en base64
        config(['documentos.firma.autofirma_activa' => true]);
        $this->enPasoDeFirma($informe)
            ->assertSee('Firmar con AutoFirma')
            ->call('firmarConAutoFirma', $base64)
            ->assertSet('errorFirma', null);

        $informe->refresh();
        $this->assertSame(EstadoInforme::Firmado, $informe->estado);
        $this->assertSame(MetodoFirma::AutofirmaCertificadoEmpleadoPublico, $informe->metodo_firma);
    }

    #[Test]
    public function tf_inf_20_el_autor_anula_con_motivo_y_el_documento_se_conserva(): void
    {
        $firmado = $this->firmado($this->tsNorte);

        $historial = Livewire::actingAs($this->tsNorte)
            ->test(InformesHistorial::class, ['historiaId' => $this->historiaAna->id])
            ->call('abrirAnulacion', $firmado->id)
            ->set('motivoAnulacion', '')
            ->call('anular')
            ->assertHasErrors('motivoAnulacion');
        $this->assertTrue($firmado->fresh()->estaFirmado(), 'Sin motivo no se anula');

        $historial->set('motivoAnulacion', 'Los datos de la unidad de convivencia eran erróneos')
            ->call('anular')
            ->assertHasNoErrors()
            ->assertSee('Anulado');

        $firmado->refresh();
        $this->assertSame(EstadoInforme::Anulado, $firmado->estado);
        $this->assertNotNull($firmado->anulado_en);
        $this->assertSame('Los datos de la unidad de convivencia eran erróneos', $firmado->motivo_anulacion);
        $this->assertSame(EstadoDocumento::Vigente, Documento::findOrFail($firmado->documento_id)->estado);

        // Un anulado no se reabre ni se vuelve a anular
        Livewire::actingAs($this->tsNorte)->test(NuevoInformeWizard::class, ['informe' => $firmado])->assertForbidden();
        Livewire::actingAs($this->tsNorte)
            ->test(InformesHistorial::class, ['historiaId' => $this->historiaAna->id])
            ->call('abrirAnulacion', $firmado->id)
            ->assertForbidden();

        // El servicio exige motivo
        $otro = $this->firmado($this->tsNorte);
        $this->expectException(\DomainException::class);
        app(ServicioFirmaInforme::class)->anular($otro, $this->tsNorte->id, '   ');
    }

    /**
     * Asistente del autor en el paso de firma.
     *
     * @param Informe $informe
     *
     * @return Testable
     */
    private function enPasoDeFirma(Informe $informe)
    {
        return Livewire::actingAs($this->tsNorte)
            ->test(NuevoInformeWizard::class, ['informe' => $informe])
            ->call('irAPaso', 3)
            ->call('irAFirma')
            ->assertSet('paso', 4);
    }
}
