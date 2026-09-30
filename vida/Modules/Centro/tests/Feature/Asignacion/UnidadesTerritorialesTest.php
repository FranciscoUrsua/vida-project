<?php

namespace Modules\Centro\Tests\Feature\Asignacion;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Organizacion\Models\Barrio;
use Modules\Organizacion\Models\Distrito;
use Modules\Organizacion\Models\SeccionCensal;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo A: unidades territoriales (TF-ASG-01, TF-ASG-02).
 *
 * @see docs/instrucciones-cli/2026-09-asignacion-tests.md
 */
class UnidadesTerritorialesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TF-ASG-01 — Código INE de sección.
     *
     * @return void
     */
    #[Test]
    public function el_codigo_ine_se_construye_con_distrito_y_seccion_rellenos_de_ceros(): void
    {
        $this->assertSame('2807921028', SeccionCensal::codigoIne('21', '28'));
        $this->assertSame('2807902028', SeccionCensal::codigoIne('2', '28'));
        $this->assertSame('2807921001', SeccionCensal::codigoIne('21', '1'));
        $this->assertNotSame(SeccionCensal::codigoIne('21', '28'), SeccionCensal::codigoIne('20', '28'));
    }

    /**
     * TF-ASG-02 — El número de barrio es único dentro de su distrito, no globalmente.
     *
     * @return void
     */
    #[Test]
    public function el_numero_de_barrio_solo_es_unico_dentro_de_su_distrito(): void
    {
        $barajas = Distrito::where('codigo', '21')->firstOrFail();
        $arganzuela = Distrito::where('codigo', '02')->firstOrFail();
        SeccionCensal::whereIn('distrito_id', [$barajas->id, $arganzuela->id])->delete();
        Barrio::whereIn('distrito_id', [$barajas->id, $arganzuela->id])->delete();

        Barrio::create(['distrito_id' => $barajas->id, 'codigo' => '214', 'codigo_en_distrito' => '4', 'nombre' => 'Timón']);

        Barrio::create(['distrito_id' => $arganzuela->id, 'codigo' => '024', 'codigo_en_distrito' => '4', 'nombre' => 'Delicias']);
        $this->assertSame(2, Barrio::where('codigo_en_distrito', '4')->whereIn('distrito_id', [$barajas->id, $arganzuela->id])->count());

        $this->expectException(QueryException::class);
        Barrio::create(['distrito_id' => $barajas->id, 'codigo' => '219', 'codigo_en_distrito' => '4', 'nombre' => 'Duplicado']);
    }

    /**
     * El catálogo oficial queda cargado por la migración de datos y es coherente.
     *
     * @return void
     */
    #[Test]
    public function la_migracion_carga_el_catalogo_oficial_coherente(): void
    {
        $this->assertSame(21, Distrito::count());
        $this->assertSame(131, Barrio::count());
        $this->assertSame(2462, SeccionCensal::count());

        $sol = Barrio::where('codigo', '016')->firstOrFail();
        $this->assertSame('6', $sol->codigo_en_distrito);
        $this->assertSame('01', $sol->distrito->codigo);

        $seccion = SeccionCensal::where('codigo_ine', '2807917120')->firstOrFail();
        $this->assertSame('120', $seccion->codigo_en_distrito);
        $this->assertSame('171', $seccion->barrio->codigo);
        $this->assertSame($seccion->distrito_id, $seccion->barrio->distrito_id);
    }
}
