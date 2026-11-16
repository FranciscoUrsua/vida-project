<?php

namespace Modules\Documentos\Tests\Feature\Informes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Documentos\Http\Livewire\NuevoInformeWizard;
use Modules\Documentos\Models\EstiloInforme;
use Modules\Documentos\Models\Informe;
use Modules\Documentos\Services\ServicioGeneracionPDF;
use Modules\Documentos\Tests\Concerns\InformesTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo B — Asistente de nuevo informe (TF-INF-05 a 10).
 */
class NuevoInformeWizardTest extends TestCase
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
    public function tf_inf_05_solo_plantillas_activas_filtrables_por_tipo(): void
    {
        $asistente = Livewire::actingAs($this->tsNorte)
            ->test(NuevoInformeWizard::class, ['historia' => $this->historiaAna])
            ->assertSee('Informe social de prueba')
            ->assertSee('Informe social con datos de la persona y valoración profesional.')
            ->assertSee('Informe psicológico de prueba')
            ->assertDontSee('Plantilla retirada');

        $asistente->set('filtroTipo', 'informe_psicologico')
            ->assertSee('Informe psicológico de prueba')
            ->assertDontSee('Informe social de prueba');

        // Una plantilla inactiva no se puede elegir por una petición directa
        Livewire::actingAs($this->tsNorte)
            ->test(NuevoInformeWizard::class, ['historia' => $this->historiaAna])
            ->call('elegirPlantilla', $this->plantillaInactiva->id)
            ->assertForbidden();
        $this->assertSame(0, Informe::count());

        // Elegir una activa crea el borrador y lleva a la redacción
        $asistente->set('filtroTipo', '')->call('elegirPlantilla', $this->plantillaSocial->id);
        $informe = Informe::sole();
        $asistente->assertRedirect(route('intervencion.informes.editar', $informe->id));
        $this->assertSame($this->tsNorte->id, $informe->autor_id);
        $this->assertSame($this->historiaAna->id, $informe->historia_social_id);
    }

    #[Test]
    public function tf_inf_06_secciones_automaticas_precargadas_y_en_lectura(): void
    {
        $informe = $this->borrador($this->tsNorte);

        Livewire::actingAs($this->tsNorte)
            ->test(NuevoInformeWizard::class, ['informe' => $informe])
            ->assertSet('paso', 2)
            ->assertSee('Datos de la persona')
            ->assertSee($this->ana->nombre)
            ->assertSee($this->ana->apellido1)
            ->assertSee('Fecha de nacimiento')
            // Escribir la sección automática por una petición directa se ignora
            ->call('guardarSeccion', 'datos', '<p>Datos inventados</p>')
            ->assertSee($this->ana->nombre);

        $this->assertArrayNotHasKey('datos', $informe->fresh()->contenido);
        $this->assertStringNotContainsString('Datos inventados', (string) json_encode($informe->fresh()->contenido));
    }

    #[Test]
    public function tf_inf_07_el_borrador_conserva_el_texto_al_retomarlo(): void
    {
        $informe = $this->borrador($this->tsNorte, contenido: []);

        Livewire::actingAs($this->tsNorte)
            ->test(NuevoInformeWizard::class, ['informe' => $informe])
            ->call('guardarSeccion', 'valoracion', '<p>La persona vive sola y <strong>necesita apoyo</strong>.</p><script>alert(1)</script>');

        $this->assertSame('<p>La persona vive sola y <strong>necesita apoyo</strong>.</p>', $informe->fresh()->contenido['valoracion']);

        Livewire::actingAs($this->tsNorte)
            ->test(NuevoInformeWizard::class, ['informe' => $informe])
            ->assertSee('necesita apoyo')
            ->assertDontSee('alert(1)');

        // Solo el autor guarda
        Livewire::actingAs($this->tsNorte2)
            ->test(NuevoInformeWizard::class, ['informe' => $informe])
            ->assertForbidden();
    }

    #[Test]
    public function tf_inf_08_no_se_pasa_de_la_vista_previa_con_obligatorias_vacias(): void
    {
        $informe = $this->borrador($this->tsNorte, contenido: ['valoracion' => '<p>&nbsp;</p>']);

        $asistente = Livewire::actingAs($this->tsNorte)
            ->test(NuevoInformeWizard::class, ['informe' => $informe])
            ->call('irAPaso', 3)
            ->assertSee('Faltan secciones obligatorias: Valoración social.')
            ->call('irAFirma')
            ->assertHasErrors('obligatorias')
            ->assertSet('paso', 3);

        // Tampoco se puede firmar saltándose el paso (petición directa)
        Livewire::actingAs($this->tsNorte)
            ->test(NuevoInformeWizard::class, ['informe' => $informe])
            ->set('pdfFirmado', $this->subida($this->pdfFirmadoDe($informe)))
            ->call('subirFirmado')
            ->assertStatus(422);
        $this->assertTrue($informe->fresh()->esBorrador());

        // Con la obligatoria rellena, sí
        $asistente->call('guardarSeccion', 'valoracion', '<p>Valoración completa.</p>')
            ->call('irAFirma')
            ->assertHasNoErrors()
            ->assertSet('paso', 4);
    }

    #[Test]
    public function tf_inf_09_los_merge_tags_se_sustituyen_en_la_vista_previa(): void
    {
        $informe = $this->borrador($this->tsNorte, contenido: ['valoracion' => '<p>Informe sobre {{nombre_ciudadano}} a {{ fecha_informe }}.</p>']);

        $texto = $this->textoDelPdf($this->actingAs($this->tsNorte)->get(route('documentos.informes.vista-previa', $informe))->assertOk()->getContent());

        $this->assertStringContainsString('Informe sobre '.$this->ana->nombre, $texto);
        $this->assertStringContainsString(now()->format('d/m/Y'), $texto);
        $this->assertStringNotContainsString('nombre_ciudadano', $texto);
    }

    #[Test]
    public function tf_inf_10_la_vista_previa_lleva_el_estilo_de_la_uo_y_el_identificador_en_el_pie(): void
    {
        EstiloInforme::create([
            'unidad_organizativa_id' => $this->cssNorte->unidad_organizativa_id,
            'nombre_unidad_cabecera' => 'Centro de Servicios Sociales Norte',
            'html_pie' => 'Ayuntamiento — pie del CSS Norte',
            'creado_por' => $this->tsNorte->id,
        ]);
        $informe = $this->borrador($this->tsNorte);

        $respuesta = $this->actingAs($this->tsNorte)->get(route('documentos.informes.vista-previa', $informe));

        $respuesta->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $texto = $this->textoDelPdf($respuesta->getContent());
        $this->assertStringContainsString('Centro de Servicios Sociales Norte', $texto);
        $this->assertStringContainsString('pie del CSS Norte', $texto);
        $this->assertStringContainsString('Ref. '.$informe->identificador(), $texto);
        $this->assertStringContainsString(ServicioGeneracionPDF::MARCA_BORRADOR, $texto);

        // El PDF para firmar es el mismo sin la marca de borrador, y se audita
        $paraFirmar = $this->actingAs($this->tsNorte)->get(route('documentos.informes.para-firmar', $informe))->assertOk();
        $this->assertStringContainsString('attachment', (string) $paraFirmar->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString(ServicioGeneracionPDF::MARCA_BORRADOR, $this->textoDelPdf($paraFirmar->getContent()));
        $this->assertDatabaseHas('audits', [
            'user_id' => $this->tsNorte->id,
            'auditable_type' => $informe->getMorphClass(),
            'auditable_id' => $informe->id,
            'accion' => 'exportar',
        ]);

        // Solo el autor
        $this->actingAs($this->tsNorte2)->get(route('documentos.informes.para-firmar', $informe))->assertForbidden();
    }

    /**
     * Texto de un PDF con pdftotext.
     *
     * @param string $pdf
     *
     * @return string
     */
    private function textoDelPdf(string $pdf): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'vida-informe');
        file_put_contents($ruta, $pdf);
        $texto = (string) shell_exec('pdftotext -enc UTF-8 '.escapeshellarg($ruta).' -');
        unlink($ruta);

        return $texto;
    }
}
