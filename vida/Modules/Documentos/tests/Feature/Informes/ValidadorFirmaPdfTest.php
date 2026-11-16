<?php

namespace Modules\Documentos\Tests\Feature\Informes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Modules\Documentos\Exceptions\FalloHerramientaExterna;
use Modules\Documentos\Exceptions\FirmaRechazadaException;
use Modules\Documentos\Models\Informe;
use Modules\Documentos\Services\Firma\ValidadorFirmaPdf;
use Modules\Documentos\Services\ServicioGeneracionPDF;
use Modules\Documentos\Tests\Concerns\InformesTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Validación mínima del PDF firmado (fase 5, F5-2): firma íntegra, mismas páginas,
 * identificador en el pie y no es la vista previa. TF-INF-13, 14 y 18, más los casos
 * base del validador.
 */
class ValidadorFirmaPdfTest extends TestCase
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
    public function acepta_el_pdf_para_firmar_firmado_y_devuelve_el_titular(): void
    {
        $informe = $this->borrador($this->tsNorte);

        $titular = app(ValidadorFirmaPdf::class)->validar($this->pdfFirmadoDe($informe), $informe);

        $this->assertSame($this->firmador->titular, $titular);
    }

    #[Test]
    public function rechaza_un_pdf_sin_firma(): void
    {
        $informe = $this->borrador($this->tsNorte);

        $this->assertRechazo('sin_firma', app(ServicioGeneracionPDF::class)->generarParaFirmar($informe), $informe);
    }

    #[Test]
    public function tf_inf_13_rechaza_una_firma_no_integra(): void
    {
        $informe = $this->borrador($this->tsNorte);
        $firmado = $this->pdfFirmadoDe($informe);

        // Un byte cambiado dentro del contenido cubierto por la firma.
        $posicion = intdiv(strlen($firmado), 3);
        $manipulado = substr_replace($firmado, $firmado[$posicion] === 'A' ? 'B' : 'A', $posicion, 1);

        $this->assertRechazo('firma_no_integra', $manipulado, $informe);
    }

    #[Test]
    public function tf_inf_14_rechaza_el_pdf_firmado_de_otro_informe(): void
    {
        $informe = $this->borrador($this->tsNorte);
        $otro = $this->borrador($this->tsNorte);

        $this->assertRechazo('no_corresponde', $this->pdfFirmadoDe($otro), $informe);
    }

    #[Test]
    public function tf_inf_14_rechaza_un_pdf_con_distinto_numero_de_paginas(): void
    {
        $informe = $this->borrador($this->tsNorte);
        $pdfUnaPagina = $this->pdfFirmadoDe($informe);

        // El informe crece hasta ocupar más de una página después de descargarlo.
        $informe->update(['contenido' => ['valoracion' => str_repeat('<p>Texto largo de la valoración social.</p>', 120)]]);

        $this->assertRechazo('no_corresponde', $pdfUnaPagina, $informe->fresh());
    }

    #[Test]
    public function rechaza_la_vista_previa_del_borrador_firmada(): void
    {
        $informe = $this->borrador($this->tsNorte);
        $vistaPrevia = $this->firmador->firmar(app(ServicioGeneracionPDF::class)->generarBorrador($informe));

        $this->assertRechazo('es_borrador', $vistaPrevia, $informe);
    }

    #[Test]
    public function rechaza_un_fichero_que_no_es_un_pdf(): void
    {
        $informe = $this->borrador($this->tsNorte);

        $this->assertRechazo('pdf_ilegible', 'esto no es un PDF', $informe);
    }

    #[Test]
    public function tf_inf_18_un_fallo_de_pdfsig_es_un_rechazo_tecnico_con_la_causa_adjunta(): void
    {
        $informe = $this->borrador($this->tsNorte);
        $pdf = $this->pdfFirmadoDe($informe);

        // pdfinfo responde; pdfsig «falla» (binario simulado que no informa de firmas).
        Process::fake([
            '*pdfsig*' => Process::result(output: '', errorOutput: 'Permission denied', exitCode: 1),
            '*' => Process::result(output: "Pages:          1\n"),
        ]);

        try {
            app(ValidadorFirmaPdf::class)->validar($pdf, $informe);
            $this->fail('Debía rechazarse');
        } catch (FirmaRechazadaException $e) {
            $this->assertSame('fallo_tecnico', $e->codigo);
            $this->assertTrue($e->tieneCausaTecnica());
            $this->assertInstanceOf(FalloHerramientaExterna::class, $e->getPrevious());
            $this->assertStringContainsString('Permission denied', $e->getPrevious()->getMessage());
            $this->assertStringNotContainsString('Permission denied', $e->getMessage());
        }
    }

    /**
     * Comprueba que el validador rechaza el PDF con el motivo indicado.
     *
     * @param string $codigo
     * @param string $pdf
     * @param Informe $informe
     *
     * @return void
     */
    private function assertRechazo(string $codigo, string $pdf, $informe): void
    {
        try {
            app(ValidadorFirmaPdf::class)->validar($pdf, $informe);
            $this->fail("Debía rechazarse con {$codigo}");
        } catch (FirmaRechazadaException $e) {
            $this->assertSame($codigo, $e->codigo, $e->getMessage());
        }
    }
}
