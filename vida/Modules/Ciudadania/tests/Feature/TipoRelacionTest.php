<?php

namespace Modules\Ciudadania\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Ciudadania\Database\Seeders\TipoRelacionSeeder;
use Modules\Ciudadania\Enums\ImplicacionFuncional;
use Modules\Ciudadania\Models\TipoRelacion;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales del catálogo de tipos de relación.
 *
 * TF-TR-01 a TF-TR-15
 *
 * @see docs/instrucciones-cli/tipo-relacion-catalogo.md
 */
class TipoRelacionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TF-TR-01: El seeder carga los 15 tipos iniciales.
     */
    #[Test]
    public function seeder_carga_quince_tipos_iniciales(): void
    {
        $this->seed(TipoRelacionSeeder::class);

        $this->assertEquals(15, TipoRelacion::count());
    }

    /**
     * TF-TR-02: El seeder es idempotente (doble ejecución no duplica registros).
     */
    #[Test]
    public function seeder_es_idempotente(): void
    {
        $this->seed(TipoRelacionSeeder::class);
        $this->seed(TipoRelacionSeeder::class);

        $this->assertEquals(15, TipoRelacion::count());
    }

    /**
     * TF-TR-03: Todos los tipos del seeder tienen eliminable = false.
     */
    #[Test]
    public function tipos_seeder_tienen_eliminable_false(): void
    {
        $this->seed(TipoRelacionSeeder::class);

        $this->assertEquals(0, TipoRelacion::where('eliminable', true)->count());
    }

    /**
     * TF-TR-04: Los tres casos de ImplicacionFuncional existen en el seeder.
     */
    #[Test]
    public function implicaciones_funcionales_existen_tras_seeder(): void
    {
        $this->seed(TipoRelacionSeeder::class);

        foreach (ImplicacionFuncional::cases() as $implicacion) {
            $this->assertTrue(
                TipoRelacion::existeImplicacion($implicacion),
                "Implicación funcional '{$implicacion->value}' no encontrada."
            );
        }
    }

    /**
     * TF-TR-05: Los tipos simétricos no tienen slug_reciproco.
     */
    #[Test]
    public function tipos_simetricos_no_tienen_slug_reciproco(): void
    {
        $this->seed(TipoRelacionSeeder::class);

        $this->assertEquals(
            0,
            TipoRelacion::where('simetrica', true)->whereNotNull('slug_reciproco')->count()
        );
    }

    /**
     * TF-TR-06: Cada slug_reciproco apunta a un slug que existe en la tabla.
     */
    #[Test]
    public function slug_reciproco_referencia_slug_existente(): void
    {
        $this->seed(TipoRelacionSeeder::class);

        $slugs = TipoRelacion::pluck('slug')->toArray();

        TipoRelacion::where('simetrica', false)
            ->whereNotNull('slug_reciproco')
            ->get()
            ->each(function ($tipo) use ($slugs) {
                $this->assertContains(
                    $tipo->slug_reciproco,
                    $slugs,
                    "El slug_reciproco '{$tipo->slug_reciproco}' de '{$tipo->slug}' no existe."
                );
            });
    }

    /**
     * TF-TR-07: No se puede eliminar un tipo con eliminable = false.
     */
    #[Test]
    public function no_elimina_tipo_no_eliminable(): void
    {
        $tipo = TipoRelacion::factory()->noEliminable()->create();

        $this->expectException(\LogicException::class);
        $tipo->delete();
    }

    /**
     * TF-TR-08: Se puede eliminar un tipo con eliminable = true.
     */
    #[Test]
    public function elimina_tipo_eliminable(): void
    {
        $tipo = TipoRelacion::factory()->create();

        $tipo->delete();

        $this->assertDatabaseMissing('tipos_relacion', ['id' => $tipo->id]);
    }

    /**
     * TF-TR-09: conImplicacionFuncional() solo devuelve tipos activos.
     */
    #[Test]
    public function con_implicacion_funcional_excluye_inactivos(): void
    {
        $this->seed(TipoRelacionSeeder::class);

        TipoRelacion::where('implicacion_funcional', 'representante')->update(['activo' => false]);

        $this->assertCount(0, TipoRelacion::conImplicacionFuncional(ImplicacionFuncional::Representante));
    }

    /**
     * TF-TR-10: existeImplicacion() devuelve false si todos los tipos están inactivos.
     */
    #[Test]
    public function existe_implicacion_false_si_todos_inactivos(): void
    {
        $this->seed(TipoRelacionSeeder::class);

        TipoRelacion::where('implicacion_funcional', 'cuidador_principal')->update(['activo' => false]);

        $this->assertFalse(TipoRelacion::existeImplicacion(ImplicacionFuncional::CuidadorPrincipal));
    }

    /**
     * TF-TR-11: tipoRecíproco() devuelve $this para tipos simétricos.
     */
    #[Test]
    public function tipo_reciproco_simetrico_devuelve_self(): void
    {
        $this->seed(TipoRelacionSeeder::class);

        $conyuge = TipoRelacion::where('slug', 'conyuge')->first();

        $this->assertEquals($conyuge->id, $conyuge->tipoRecíproco()->id);
    }

    /**
     * TF-TR-12: tipoRecíproco() devuelve el tipo correcto para asimétricos (padre → hijo).
     */
    #[Test]
    public function tipo_reciproco_asimetrico_devuelve_tipo_correcto(): void
    {
        $this->seed(TipoRelacionSeeder::class);

        $padre = TipoRelacion::where('slug', 'padre')->first();
        $hijo = TipoRelacion::where('slug', 'hijo')->first();

        $this->assertEquals($hijo->id, $padre->tipoRecíproco()->id);
    }

    /**
     * TF-TR-13: opcionesParaSelect() excluye tipos inactivos.
     */
    #[Test]
    public function opciones_para_select_excluye_inactivos(): void
    {
        $this->seed(TipoRelacionSeeder::class);

        TipoRelacion::where('slug', 'acogido')->update(['activo' => false]);

        $opciones = TipoRelacion::opcionesParaSelect();

        $this->assertArrayNotHasKey('acogido', $opciones);
        $this->assertCount(14, $opciones);
    }

    /**
     * TF-TR-14: La reciprocidad de los pares asimétricos es coherente y bidireccional.
     */
    #[Test]
    public function reciprocidad_coherente_en_pares(): void
    {
        $this->seed(TipoRelacionSeeder::class);

        $pares = [
            ['padre', 'hijo'],
            ['abuelo', 'nieto'],
            ['tutor_legal', 'tutelado'],
            ['representante', 'representado'],
            ['cuidador_principal', 'persona_cuidada'],
            ['acogedor', 'acogido'],
        ];

        foreach ($pares as [$slugA, $slugB]) {
            $a = TipoRelacion::where('slug', $slugA)->first();
            $b = TipoRelacion::where('slug', $slugB)->first();

            $this->assertEquals($slugB, $a->slug_reciproco,
                "El recíproco de '$slugA' debería ser '$slugB'.");
            $this->assertEquals($slugA, $b->slug_reciproco,
                "El recíproco de '$slugB' debería ser '$slugA'.");
        }
    }

    /**
     * TF-TR-15: ImplicacionFuncional::from() lanza ValueError para valor desconocido.
     */
    #[Test]
    public function implicacion_funcional_rechaza_valor_invalido(): void
    {
        $this->expectException(\ValueError::class);

        ImplicacionFuncional::from('valor_inexistente');
    }
}
