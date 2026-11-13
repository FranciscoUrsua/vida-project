<?php

namespace Modules\Centro\Tests\Feature\Asignacion;

use App\Filament\Resources\CentroResource\Pages\ListCentros;
use App\Models\CatalogoSistema;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Modules\Centro\Models\AmbitoTerritorial;
use Modules\Centro\Tests\Concerns\AsignacionTestSetup;
use Modules\Organizacion\Models\Barrio;
use Modules\Organizacion\Models\SeccionCensal;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo B: ámbitos y cobertura (TF-ASG-05 a TF-ASG-08).
 *
 * @see docs/instrucciones-cli/2026-09-asignacion-tests.md
 */
class AmbitoCoberturaTest extends TestCase
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
     * TF-ASG-05 [negativo] — Una misma unidad no puede estar en dos centros del mismo tipo.
     *
     * @return void
     */
    #[Test]
    public function no_se_puede_anadir_una_unidad_que_ya_tiene_otro_centro_del_mismo_tipo(): void
    {
        try {
            $this->anadirAmbito($this->cssSur, 'barrios', Barrio::where('codigo', '011')->value('id'));
            $this->fail('Debía rechazarse el solapamiento');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('CSS Norte', $e->getMessage());
        }

        $this->assertSame(0, AmbitoTerritorial::where('centro_id', $this->cssSur->id)->where('tipo', 'barrios')
            ->where('referencia_id', Barrio::where('codigo', '011')->value('id'))->count());
    }

    /**
     * TF-ASG-06 — Entre tipos distintos, o con un centro de libre elección, no hay solapamiento.
     *
     * @return void
     */
    #[Test]
    public function la_misma_unidad_se_permite_en_un_centro_de_libre_eleccion(): void
    {
        $ambito = $this->anadirAmbito($this->ciam, 'barrios', Barrio::where('codigo', '011')->value('id'));

        $this->assertTrue($ambito->exists);
    }

    /**
     * TF-ASG-07 — Una sección dentro de un distrito de otro centro no es solapamiento.
     *
     * @return void
     */
    #[Test]
    public function distinto_nivel_no_es_solapamiento(): void
    {
        $cssEste = $this->crearCentro('CSS Este', $this->tipoCss);

        $ambito = $this->anadirAmbito($cssEste, 'secciones_censales', SeccionCensal::where('codigo_ine', '2807902001')->value('id'));

        $this->assertTrue($ambito->exists);
    }

    /**
     * TF-ASG-08 — La comprobación de cobertura lista las secciones sin centro.
     *
     * @return void
     */
    #[Test]
    public function la_comprobacion_de_cobertura_lista_las_secciones_sin_centro(): void
    {
        Artisan::call('centros:comprobar-cobertura', ['tipo_centro' => $this->tipoCss]);
        $this->assertStringContainsString('Todas las secciones censales activas tienen centro', Artisan::output());

        AmbitoTerritorial::where('centro_id', $this->cssSur->id)->where('tipo', 'barrios')->delete();

        Artisan::call('centros:comprobar-cobertura', ['tipo_centro' => $this->tipoCss]);
        $salida = Artisan::output();
        $this->assertStringContainsString('2807901003', $salida);
        $this->assertStringNotContainsString('2807901001', $salida);
        $this->assertStringNotContainsString('2807902001', $salida);
    }

    /**
     * La misma comprobación desde Filament, como acción del listado de centros.
     *
     * @return void
     */
    #[Test]
    public function la_accion_de_filament_avisa_de_las_secciones_sin_centro(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('adm_sistema');
        CatalogoSistema::firstOrCreate(
            ['grupo' => 'centro.tipo', 'clave' => $this->tipoCss],
            ['etiqueta' => 'Centro de servicios sociales', 'orden' => 1, 'activo' => true],
        );

        Livewire::actingAs($admin)
            ->test(ListCentros::class)
            ->callAction('comprobar_cobertura', ['tipo_centro' => $this->tipoCss])
            ->assertNotified('Todas las secciones censales tienen centro de este tipo.');

        AmbitoTerritorial::where('centro_id', $this->cssSur->id)->where('tipo', 'barrios')->delete();

        Livewire::actingAs($admin)
            ->test(ListCentros::class)
            ->callAction('comprobar_cobertura', ['tipo_centro' => $this->tipoCss])
            ->assertNotified('1 secciones censales sin centro');
    }
}
