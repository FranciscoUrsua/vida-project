<?php

namespace Modules\Prestaciones\Tests\Feature;

use App\Models\CatalogoSistema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Prestaciones\Database\Seeders\CatalogosSistemaSeeder;
use Modules\Prestaciones\Database\Seeders\PrestacionesSeeder;
use Modules\Prestaciones\Models\Prestacion;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del seeder del módulo Prestaciones.
 *
 * Verifican que los seeders cargan los datos correctamente y son idempotentes.
 */
class PrestacionSeederTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // CatalogosSistemaSeeder
    // -------------------------------------------------------------------------

    #[Test]
    public function el_seeder_de_catalogos_carga_los_ocho_objetivos_generales(): void
    {
        (new CatalogosSistemaSeeder())->run();

        $objetivos = CatalogoSistema::where('grupo', 'prestacion.objetivo_general')->get();

        $this->assertCount(8, $objetivos);

        $claves = $objetivos->pluck('clave')->sort()->values()->toArray();
        $this->assertEquals(['01', '02', '03', '04', '05', '06', '07', '08'], $claves);
    }

    #[Test]
    public function el_seeder_de_catalogos_es_idempotente(): void
    {
        (new CatalogosSistemaSeeder())->run();
        $conteo = CatalogoSistema::count();

        (new CatalogosSistemaSeeder())->run();

        $this->assertEquals($conteo, CatalogoSistema::count());
    }

    // -------------------------------------------------------------------------
    // PrestacionesSeeder
    // -------------------------------------------------------------------------

    #[Test]
    public function el_seeder_de_prestaciones_carga_las_prestaciones_esperadas(): void
    {
        (new CatalogosSistemaSeeder())->run();
        (new PrestacionesSeeder())->run();

        // El seeder tiene 49 prestaciones implementadas del catálogo oficial
        $this->assertEquals(49, Prestacion::count());
    }

    #[Test]
    public function el_seeder_de_prestaciones_es_idempotente(): void
    {
        (new CatalogosSistemaSeeder())->run();
        (new PrestacionesSeeder())->run();
        $conteo = Prestacion::count();

        (new PrestacionesSeeder())->run();

        $this->assertEquals($conteo, Prestacion::count());
    }

    #[Test]
    public function todas_las_prestaciones_del_seeder_tienen_campos_obligatorios(): void
    {
        (new CatalogosSistemaSeeder())->run();
        (new PrestacionesSeeder())->run();

        $invalidas = Prestacion::whereNull('codigo')
            ->orWhereNull('nombre')
            ->orWhereNull('tipo_prestacion')
            ->orWhereNull('nivel_garantia')
            ->orWhere('codigo', '')
            ->orWhere('nombre', '')
            ->count();

        $this->assertEquals(0, $invalidas);
    }

    #[Test]
    public function los_codigos_del_seeder_son_unicos(): void
    {
        (new CatalogosSistemaSeeder())->run();
        (new PrestacionesSeeder())->run();

        $total    = Prestacion::count();
        $unicos   = Prestacion::distinct('codigo')->count();

        $this->assertEquals($total, $unicos);
    }
}
