<?php

namespace Modules\Centro\Tests\Feature\Asignacion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Centro\Enums\ModoAsignacionCentro;
use Modules\Centro\Models\AsignacionCentro;
use Modules\Centro\Services\Asignacion\AsignacionCentroService;
use Modules\Centro\Tests\Concerns\AsignacionTestSetup;
use Modules\Ciudadania\Http\Livewire\FichaCiudadanoPage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * «Asignar a este centro» en la ficha del ciudadano: con «Sin centro asignado», el profesional
 * asigna la persona a su centro activo solo si es de libre elección (decisión del desarrollador,
 * 27 nov 2026). Los centros por domicilio siguen pasando por la bandeja del supervisor (RN-09).
 */
class AsignarDesdeFichaTest extends TestCase
{
    use AsignacionTestSetup;
    use RefreshDatabase;

    /**
     * Monta el escenario común.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->montarEscenarioAsignacion();
    }

    /**
     * En un centro de libre elección, el botón aparece y asigna por elección.
     *
     * @return void
     */
    #[Test]
    public function en_un_centro_de_libre_eleccion_asigna_la_persona_a_ese_centro(): void
    {
        $tsCiam = $this->crearProfesionalEnCentro('ts.ciam', $this->ciam, $this->cargoTs, 35);

        Livewire::actingAs($tsCiam)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->pedro->id])
            ->assertSee('Sin centro asignado')
            ->assertSee('Asignar a este centro')
            ->call('asignarAEsteCentro')
            ->assertDontSee('Asignar a este centro')
            ->assertSee('CIAM');

        $vigente = AsignacionCentro::vigentes()->where('ciudadano_id', $this->pedro->id)->sole();
        $this->assertSame($this->ciam->id, $vigente->centro_id);
        $this->assertSame(ModoAsignacionCentro::Eleccion, $vigente->modo);
        $this->assertSame($tsCiam->id, $vigente->asignado_por_id);
    }

    /**
     * [negativo] En un centro por domicilio no hay botón y la llamada directa se rechaza.
     *
     * @return void
     */
    #[Test]
    public function en_un_centro_por_domicilio_no_se_ofrece_ni_se_admite(): void
    {
        Livewire::actingAs($this->ts1)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->pedro->id])
            ->assertSee('Sin centro asignado')
            ->assertDontSee('Asignar a este centro')
            ->call('asignarAEsteCentro')
            ->assertForbidden();

        $this->assertSame(0, AsignacionCentro::where('ciudadano_id', $this->pedro->id)->count());
    }

    /**
     * [negativo] Con algún centro vigente la ficha no dice «Sin centro asignado» y no se ofrece.
     *
     * @return void
     */
    #[Test]
    public function con_un_centro_vigente_no_se_ofrece(): void
    {
        $tsCiam = $this->crearProfesionalEnCentro('ts.ciam', $this->ciam, $this->cargoTs, 35);
        app(AsignacionCentroService::class)->asignarManual($this->pedro, $this->cssNorte, 'Prueba', $this->supervisor);

        Livewire::actingAs($tsCiam)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->pedro->id])
            ->assertDontSee('Sin centro asignado')
            ->assertDontSee('Asignar a este centro')
            ->call('asignarAEsteCentro')
            ->assertForbidden();

        $this->assertSame(0, AsignacionCentro::where('ciudadano_id', $this->pedro->id)->where('centro_id', $this->ciam->id)->count());
    }
}
