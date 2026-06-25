<?php

namespace Modules\Intervencion\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intervencion\Database\Seeders\TipoPlanSeeder;
use Modules\Intervencion\Models\IndicadorCatalogo;
use Modules\Intervencion\Models\ObjetivoCatalogo;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\PlanObjetivo;
use Modules\Intervencion\Models\PlanObjetivoIndicador;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Intervencion\Models\TipoPlan;
use Tests\TestCase;

/**
 * Tests funcionales de objetivos con indicadores.
 * Nomenclatura: TF-OI-XX
 */
class ObjetivosIndicadoresTest extends TestCase
{
    use RefreshDatabase;

    private function crearPlan(): PlanDeIntervencion
    {
        $this->seed(TipoPlanSeeder::class);

        return PlanDeIntervencion::factory()->create([
            'tipo_plan_id' => TipoPlan::first()->id,
        ]);
    }

    // TF-OI-01: Se puede crear un indicador de catálogo para un objetivo
    public function test_crear_indicador_catalogo(): void
    {
        $tipo = TipoPlan::factory()->create();
        $objetivo = ObjetivoCatalogo::create([
            'tipo_plan_id' => $tipo->id,
            'nivel' => 'general',
            'texto' => 'Mejorar la situación económica',
            'orden' => 1,
        ]);

        $indicador = IndicadorCatalogo::create([
            'objetivo_catalogo_id' => $objetivo->id,
            'descripcion' => 'El ciudadano ha accedido a prestaciones económicas',
            'tipo_valoracion' => 'conseguido_proceso_no',
        ]);

        $this->assertEquals($objetivo->id, $indicador->objetivoCatalogo->id);
        $this->assertEquals(
            ['conseguido', 'en_proceso', 'no_conseguido'],
            $indicador->valoresPosibles()
        );
    }

    // TF-OI-02: Los valores posibles son correctos para cada tipo
    public function test_valores_posibles_por_tipo(): void
    {
        $this->assertEquals(
            ['conseguido', 'en_proceso', 'no_conseguido'],
            array_keys(IndicadorCatalogo::etiquetasValoración('conseguido_proceso_no'))
        );
        $this->assertEquals(
            ['favorable', 'se_mantiene', 'desfavorable'],
            array_keys(IndicadorCatalogo::etiquetasValoración('favorable_mantiene_desfavorable'))
        );
        $this->assertEquals(
            ['si', 'no'],
            array_keys(IndicadorCatalogo::etiquetasValoración('si_no'))
        );
    }

    // TF-OI-03: instanciarIndicador crea el indicador del plan desde el catálogo
    public function test_instanciar_indicador_desde_catalogo(): void
    {
        $plan = $this->crearPlan();
        $tipo = TipoPlan::first();

        $objCatalogo = ObjetivoCatalogo::create([
            'tipo_plan_id' => $tipo->id,
            'nivel' => 'general',
            'texto' => 'Objetivo de prueba',
            'orden' => 1,
        ]);
        $indCatalogo = IndicadorCatalogo::create([
            'objetivo_catalogo_id' => $objCatalogo->id,
            'descripcion' => 'Indicador de prueba',
            'tipo_valoracion' => 'si_no',
        ]);

        $planObj = PlanObjetivo::create([
            'plan_id' => $plan->id,
            'objetivo_catalogo_id' => $objCatalogo->id,
            'nivel' => 'general',
            'texto' => $objCatalogo->texto,
            'estado' => 'pendiente',
            'orden' => 1,
        ]);

        $indicador = $planObj->instanciarIndicador();

        $this->assertEquals($indCatalogo->id, $indicador->indicador_catalogo_id);
        $this->assertEquals('si_no', $indicador->tipo_valoracion);
        $this->assertNull($indicador->valoracion_actual);
    }

    // TF-OI-04: instanciarIndicador funciona para objetivos ex-novo
    public function test_instanciar_indicador_exnovo(): void
    {
        $plan = $this->crearPlan();

        $planObj = PlanObjetivo::create([
            'plan_id' => $plan->id,
            'nivel' => 'especifico',
            'texto' => 'Objetivo ex-novo del TSR',
            'estado' => 'pendiente',
            'orden' => 1,
        ]);

        $indicador = $planObj->instanciarIndicador(
            'Indicador creado por el profesional',
            'favorable_mantiene_desfavorable'
        );

        $this->assertNull($indicador->indicador_catalogo_id);
        $this->assertEquals('Indicador creado por el profesional', $indicador->descripcion);
        $this->assertEquals('favorable_mantiene_desfavorable', $indicador->tipo_valoracion);
    }

    // TF-OI-05: registrarValoracion guarda el valor correcto
    public function test_registrar_valoracion(): void
    {
        $plan = $this->crearPlan();

        $planObj = PlanObjetivo::create([
            'plan_id' => $plan->id, 'nivel' => 'general',
            'texto' => 'Objetivo', 'estado' => 'pendiente', 'orden' => 1,
        ]);

        $indicador = PlanObjetivoIndicador::create([
            'plan_objetivo_id' => $planObj->id,
            'descripcion' => 'Test',
            'tipo_valoracion' => 'conseguido_proceso_no',
        ]);

        $indicador->registrarValoracion('en_proceso');

        $this->assertEquals('en_proceso', $indicador->fresh()->valoracion_actual);
        $this->assertNotNull($indicador->fresh()->fecha_valoracion);
    }

    // TF-OI-06: registrarValoracion rechaza valor inválido
    public function test_valoracion_invalida_lanza_excepcion(): void
    {
        $plan = $this->crearPlan();

        $planObj = PlanObjetivo::create([
            'plan_id' => $plan->id, 'nivel' => 'general',
            'texto' => 'Objetivo', 'estado' => 'pendiente', 'orden' => 1,
        ]);

        $indicador = PlanObjetivoIndicador::create([
            'plan_objetivo_id' => $planObj->id,
            'descripcion' => 'Test',
            'tipo_valoracion' => 'si_no',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $indicador->registrarValoracion('favorable'); // no válido para si_no
    }

    // TF-OI-07: estaValorado devuelve false/true correctamente
    public function test_esta_valorado(): void
    {
        $plan = $this->crearPlan();

        $planObj = PlanObjetivo::create([
            'plan_id' => $plan->id, 'nivel' => 'general',
            'texto' => 'Objetivo', 'estado' => 'pendiente', 'orden' => 1,
        ]);

        $indicador = PlanObjetivoIndicador::create([
            'plan_objetivo_id' => $planObj->id,
            'descripcion' => 'Test',
            'tipo_valoracion' => 'si_no',
        ]);

        $this->assertFalse($indicador->estaValorado());
        $indicador->registrarValoracion('si');
        $this->assertTrue($indicador->fresh()->estaValorado());
    }

    // TF-OI-08: Un objetivo específico se puede vincular a un tipo de ficha
    public function test_objetivo_especifico_con_tipo_ficha(): void
    {
        $tipo = TipoPlan::factory()->create();
        $tipoFicha = TipoFicha::factory()->create(['nombre' => 'Situación de vivienda']);

        $objetivo = ObjetivoCatalogo::create([
            'tipo_plan_id' => $tipo->id,
            'tipo_ficha_id' => $tipoFicha->id,
            'nivel' => 'especifico',
            'texto' => 'Mejorar las condiciones de habitabilidad',
            'orden' => 1,
        ]);

        $this->assertEquals('Situación de vivienda', $objetivo->tipoFicha->nombre);
    }
}
