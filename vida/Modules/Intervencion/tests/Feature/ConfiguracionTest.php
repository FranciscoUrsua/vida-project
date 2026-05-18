<?php

namespace Modules\Intervencion\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intervencion\Database\Seeders\IntervencionSeeder;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Intervencion\Models\TipoValoracion;
use Modules\Intervencion\Models\TipoValoracionFicha;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo G: Integridad de configuración.
 *
 * @see docs/modulo-intervencion.md §4, §10 Grupo G
 */
class ConfiguracionTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * TF-INT-G01: TipoFicha con schema JSON inválido no puede guardarse.
     */
    #[Test]
    public function tipo_ficha_con_schema_json_invalido_no_puede_guardarse(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TipoFicha::create([
            'nombre' => 'Ficha inválida',
            'schema' => 'esto no es json{',
            'activo' => true,
        ]);
    }

    /**
     * TF-INT-G02: El seeder produce exactamente los registros esperados.
     */
    #[Test]
    public function seeder_produce_exactamente_los_registros_esperados(): void
    {
        $this->seed(IntervencionSeeder::class);

        $countFichas        = TipoFicha::count();
        $countValoraciones  = TipoValoracion::count();
        $countPivot         = TipoValoracionFicha::count();

        $this->assertEquals(3, $countFichas, 'El seeder debe crear exactamente 3 tipo_fichas');
        $this->assertEquals(1, $countValoraciones, 'El seeder debe crear exactamente 1 tipo_valoracion');
        $this->assertEquals(3, $countPivot, 'El seeder debe crear exactamente 3 registros en tipo_valoracion_fichas');

        // Verificar que todos los schemas son arrays válidos con al menos un campo
        TipoFicha::all()->each(function (TipoFicha $ficha) {
            $this->assertIsArray($ficha->schema, "El schema de '{$ficha->nombre}' debe ser un array");
            $this->assertNotEmpty($ficha->schema, "El schema de '{$ficha->nombre}' no puede estar vacío");
        });
    }

    /**
     * TF-INT-G03: TipoFicha inactivo no aparece en scopes de fichas activas.
     */
    #[Test]
    public function tipo_ficha_inactivo_no_aparece_en_scope_activos(): void
    {
        TipoFicha::factory()->create(['activo' => true,  'nombre' => 'Ficha activa']);
        TipoFicha::factory()->create(['activo' => false, 'nombre' => 'Ficha inactiva']);

        $activas = TipoFicha::activos()->get();

        $this->assertCount(1, $activas, 'El scope activos debe devolver solo los tipos de ficha activos');
        $this->assertEquals('Ficha activa', $activas->first()->nombre);
    }
}
