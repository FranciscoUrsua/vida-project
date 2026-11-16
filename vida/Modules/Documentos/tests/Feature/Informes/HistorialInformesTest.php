<?php

namespace Modules\Documentos\Tests\Feature\Informes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Documentos\Http\Livewire\InformesHistorial;
use Modules\Documentos\Models\Informe;
use Modules\Documentos\Services\LecturaDocumentoService;
use Modules\Documentos\Tests\Concerns\InformesTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo A — Historial de informes (TF-INF-01 a 04).
 */
class HistorialInformesTest extends TestCase
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
    public function tf_inf_01_lista_los_informes_con_plantilla_tipo_autor_estado_y_fechas(): void
    {
        $borrador = $this->borrador($this->tsNorte, $this->plantillaPsico, ['evaluacion' => '<p>En curso</p>']);
        $firmado = $this->firmado($this->tsNorte);

        Livewire::actingAs($this->tsNorte)
            ->test(InformesHistorial::class, ['historiaId' => $this->historiaAna->id])
            ->assertSee('Informe psicológico de prueba')
            ->assertSee('Informe social de prueba')
            ->assertSee('Informe psicológico')
            ->assertSee('Informe social')
            ->assertSee('Ts.norte Prueba')
            ->assertSee('Borrador')
            ->assertSee('Firmado')
            ->assertSee($borrador->identificador())
            ->assertSee($firmado->identificador())
            ->assertSee('firmado el '.$firmado->firmado_en->format('d/m/Y'));
    }

    #[Test]
    public function tf_inf_02_acciones_segun_estado_y_autor(): void
    {
        $this->borrador($this->tsNorte);
        $this->firmado($this->tsNorte);

        Livewire::actingAs($this->tsNorte)
            ->test(InformesHistorial::class, ['historiaId' => $this->historiaAna->id])
            ->assertSee('Continuar')
            ->assertSee('Eliminar borrador')
            ->assertSee('Anular')
            ->assertSee('Ver PDF');

        Livewire::actingAs($this->tsNorte2)
            ->test(InformesHistorial::class, ['historiaId' => $this->historiaAna->id])
            ->assertSee('Ver PDF')
            ->assertDontSee('Continuar')
            ->assertDontSee('Eliminar borrador')
            ->assertDontSee('Anular');
    }

    #[Test]
    public function tf_inf_03_el_autor_elimina_su_borrador_y_un_firmado_no_se_puede_eliminar(): void
    {
        $borrador = $this->borrador($this->tsNorte);
        $firmado = $this->firmado($this->tsNorte);

        $componente = Livewire::actingAs($this->tsNorte)
            ->test(InformesHistorial::class, ['historiaId' => $this->historiaAna->id])
            ->call('eliminarBorrador', $borrador->id)
            ->assertSee('Borrador eliminado.');

        $this->assertSoftDeleted('informes', ['id' => $borrador->id]);

        // Un firmado: sin la acción y rechazado en el servidor
        $componente->assertDontSee('Eliminar borrador');
        Livewire::actingAs($this->tsNorte)
            ->test(InformesHistorial::class, ['historiaId' => $this->historiaAna->id])
            ->call('eliminarBorrador', $firmado->id)
            ->assertForbidden();
        $this->assertNotSoftDeleted('informes', ['id' => $firmado->id]);

        // Tampoco otro profesional elimina el borrador ajeno
        $ajeno = $this->borrador($this->tsNorte);
        Livewire::actingAs($this->tsNorte2)
            ->test(InformesHistorial::class, ['historiaId' => $this->historiaAna->id])
            ->call('eliminarBorrador', $ajeno->id)
            ->assertForbidden();
    }

    #[Test]
    public function tf_inf_04_consulta_basica_no_ve_informes_ni_su_pdf(): void
    {
        $firmado = $this->firmado($this->tsNorte);

        Livewire::actingAs($this->ventanillaNorte)
            ->test(InformesHistorial::class, ['historiaId' => $this->historiaAna->id])
            ->assertDontSee('Informes')
            ->assertDontSee('Informe social de prueba')
            ->assertDontSee('Ver PDF');

        $url = app(LecturaDocumentoService::class)->urlTemporal($firmado->documento);
        $this->actingAs($this->ventanillaNorte)->get($url)->assertForbidden();
        $this->actingAs($this->ventanillaNorte)->get(route('documentos.informes.vista-previa', $this->borrador($this->tsNorte)))->assertForbidden();
        $this->assertSame(1, Informe::firmados()->count());
    }
}
