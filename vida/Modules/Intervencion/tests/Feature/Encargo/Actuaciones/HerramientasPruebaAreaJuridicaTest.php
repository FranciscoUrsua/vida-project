<?php

namespace Modules\Intervencion\Tests\Feature\Encargo\Actuaciones;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Documentos\Models\PlantillaInforme;
use Modules\Documentos\Support\InformeEncargo;
use Modules\Escalas\Models\TipoEscala;
use Modules\Intervencion\Database\Seeders\HerramientasPruebaAreaJuridicaSeeder;
use Modules\Intervencion\Models\AreaEncargo;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Intervencion\Services\Actuaciones\ContextoActuacion;
use Modules\Intervencion\Services\Actuaciones\PaseEscalaService;
use Modules\Intervencion\Services\CajaHerramientasEncargo;
use Modules\Intervencion\Services\EncargoService;
use Modules\Intervencion\Tests\Concerns\EncargoTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Herramientas de prueba del área jurídica (26 nov 2026): una ficha de valoración, una escala y
 * una plantilla de informe para probar en staging el circuito completo de las actuaciones del
 * encargo. El seeder es idempotente y la plantilla solo usa fuentes permitidas en un encargo (EA-9).
 */
class HerramientasPruebaAreaJuridicaTest extends TestCase
{
    use EncargoTestSetup;
    use RefreshDatabase;

    /**
     * Escenario del encargo (usuarios y UO) con el área jurídica sembrada por la migración.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEncargo();
    }

    /** El seeder deja en el área jurídica una ficha, una escala y una plantilla válidas, sin duplicar al repetirlo. */
    #[Test]
    public function siembra_las_herramientas_del_area_juridica_de_forma_idempotente(): void
    {
        (new HerramientasPruebaAreaJuridicaSeeder)->run();
        (new HerramientasPruebaAreaJuridicaSeeder)->run();

        $area = AreaEncargo::where('codigo', 'juridica')->sole();
        $ficha = TipoFicha::where('nombre', HerramientasPruebaAreaJuridicaSeeder::FICHA)->sole();
        $escala = TipoEscala::where('codigo', HerramientasPruebaAreaJuridicaSeeder::ESCALA)->sole();
        $plantilla = PlantillaInforme::where('nombre', HerramientasPruebaAreaJuridicaSeeder::PLANTILLA)->sole();

        $this->assertTrue($area->tiposFicha()->whereKey($ficha->id)->exists());
        $this->assertTrue($area->tiposEscala()->whereKey($escala->id)->exists());
        $this->assertTrue($area->plantillasInforme()->whereKey($plantilla->id)->exists());
        $this->assertSame([], InformeEncargo::infracciones($plantilla));
        $this->assertSame('valoracion', $ficha->uso);

        // Un encargo jurídico las ofrece y la escala puntúa con su schema
        $encargo = app(EncargoService::class)->encargar(
            $this->historiaAna, null, null, $this->ciamVallecas, $area, 'Prueba de herramientas', [], $this->tsNorte,
        );
        app(EncargoService::class)->aceptar($encargo, $this->abogadaVallecas, $this->directoraVallecas);
        $caja = app(CajaHerramientasEncargo::class);
        $this->assertTrue($caja->ofreceFicha($encargo->refresh(), $ficha->id));
        $this->assertTrue($caja->ofreceEscala($encargo, $escala->id));
        $this->assertTrue($caja->ofrecePlantilla($encargo, $plantilla->id));

        $this->actingAs($this->abogadaVallecas);
        $respuestas = collect($escala->schema['secciones'][0]['items'])->mapWithKeys(fn (array $item): array => [$item['id'] => 2])->all();
        $pase = app(PaseEscalaService::class)->registrar(ContextoActuacion::desdeEncargo($encargo, $this->abogadaVallecas), $escala, $respuestas);
        $this->assertSame(10, $pase->score_total);
    }
}
