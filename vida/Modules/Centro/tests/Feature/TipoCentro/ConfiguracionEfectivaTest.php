<?php

namespace Modules\Centro\Tests\Feature\TipoCentro;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;
use Modules\Centro\Tests\Concerns\TipoCentroTestSetup;
use Modules\Intervencion\Models\TipoFicha;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Configuración efectiva de un centro: TF-TCE-11 (UO funcional), 20 (prestaciones),
 * 25 (fichas) y 27 (segmentos, en el servicio).
 *
 * @see docs/instrucciones-cli/2026-10-tipo-centro-tests.md
 */
class ConfiguracionEfectivaTest extends TestCase
{
    use RefreshDatabase;
    use TipoCentroTestSetup;

    /**
     * Escenario común de Tipo de Centro.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarTipoCentro();
    }

    /**
     * Servicio bajo prueba.
     *
     * @return ConfiguracionEfectivaCentro
     */
    private function config(): ConfiguracionEfectivaCentro
    {
        return app(ConfiguracionEfectivaCentro::class);
    }

    /** TF-TCE-11 — La UO funcional es la del tipo; la UO del centro y su padre orgánico no cambian. */
    #[Test]
    public function tf_tce_11_uo_funcional_y_uo_organica(): void
    {
        $this->assertSame($this->uoIgualdad->id, $this->config()->uoFuncional($this->ciamVallecas)->id);
        $this->assertSame($this->uoCiamVallecas->id, $this->ciamVallecas->unidad_organizativa_id);
        $this->assertSame($this->uoJuntaVallecas->id, $this->uoCiamVallecas->fresh()->parent_id);

        $this->tipoCiam->update(['unidad_organizativa_id' => $this->uoPrimaria->id]);

        $centro = $this->ciamVallecas->fresh();
        $this->assertSame($this->uoPrimaria->id, $this->config()->uoFuncional($centro)->id);
        $this->assertSame($this->uoCiamVallecas->id, $centro->unidad_organizativa_id);
        $this->assertSame($this->uoJuntaVallecas->id, $this->uoCiamVallecas->fresh()->parent_id);
    }

    /**
     * TF-TCE-20 — Las prestaciones del centro son las del tipo. Con D2 (addenda §6.2)
     * la tabla `centro_prestacion` ya no existe, así que no hay fila antigua que ignorar.
     */
    #[Test]
    public function tf_tce_20_prestaciones_del_centro_son_las_del_tipo(): void
    {
        $this->assertFalse(Schema::hasTable('centro_prestacion'));

        $ids = $this->config()->prestaciones($this->cssNorte)->pluck('id')->sort()->values()->all();
        $this->assertSame(collect([$this->p1->id, $this->p2->id])->sort()->values()->all(), $ids);
        $this->assertNotContains($this->p3->id, $ids);
    }

    /** TF-TCE-25 — Fichas: el CIAM solo la de VG; el CSS, sin fichas en el tipo, todas las activas. */
    #[Test]
    public function tf_tce_25_fichas_de_valoracion(): void
    {
        $this->assertSame([$this->fichaVG->id], $this->config()->tiposFicha($this->ciamVallecas)->pluck('id')->all());

        $todas = TipoFicha::where('activo', true)->pluck('id')->sort()->values()->all();
        $this->assertSame($todas, $this->config()->tiposFicha($this->cssNorte)->pluck('id')->sort()->values()->all());
    }

    /** TF-TCE-27 — El público destinatario es del tipo. */
    #[Test]
    public function tf_tce_27_segmentos_solo_en_el_tipo(): void
    {
        $this->assertSame(['vg'], $this->config()->segmentos($this->ciamVallecas)->pluck('slug')->all());
        $this->assertFalse(Schema::hasTable('centro_segmento_poblacion'));
    }
}
