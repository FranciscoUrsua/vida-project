<?php

namespace Modules\Prestaciones\Tests\Feature\Solicitudes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Documentos\Http\Livewire\DocumentosCiudadano;
use Modules\Documentos\Services\LecturaDocumentoService;
use Modules\Prestaciones\Tests\Concerns\SolicitudesTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Documentos para tramitación tras la fase 4 (decisión del desarrollador, 15 nov 2026).
 *
 * Tramitación no ve la carpeta completa de la persona en su ficha (solo sus documentos del
 * día), pero sí los documentos de las personas cuyas solicitudes tramita: los vinculados a
 * la solicitud y, desde la solicitud, los de la carpeta (RN-10 de la fase 3 bis).
 *
 * TF-CBA-21 ter.
 */
class DocumentosTramitacionTest extends TestCase
{
    use RefreshDatabase;
    use SolicitudesTestSetup;

    /**
     * Prepara el escenario de solicitudes.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarSolicitudes();
    }

    /** TF-CBA-21 ter — Tramitación abre los documentos de sus solicitudes, no la carpeta en la ficha. */
    #[Test]
    public function tf_cba_21_ter_tramitacion_ve_los_documentos_de_sus_solicitudes(): void
    {
        $this->sadEnBandeja();
        $deAna = $this->documentoEnCarpeta($this->ana, $this->tipoIdentidad);
        $deMarta = $this->documentoEnCarpeta($this->marta, $this->tipoIdentidad);

        // En la ficha, la tarjeta de documentos solo muestra los suyos del día: ninguno
        $tarjeta = Livewire::actingAs($this->tramitadora1)->test(DocumentosCiudadano::class, ['ciudadanoId' => $this->ana->id]);
        $this->assertCount(0, $tarjeta->instance()->documentos);

        $lectura = app(LecturaDocumentoService::class);

        // Persona con una solicitud en su bandeja: lo abre
        $this->actingAs($this->tramitadora1)->get($lectura->urlTemporal($deAna))->assertOk();

        // Persona sin solicitud suya: no
        $this->actingAs($this->tramitadora1)->get($lectura->urlTemporal($deMarta))->assertForbidden();

        // Tramitación de otra unidad: tampoco
        $this->actingAs($this->tramitadoraOtra)->get($lectura->urlTemporal($deAna))->assertForbidden();
    }
}
