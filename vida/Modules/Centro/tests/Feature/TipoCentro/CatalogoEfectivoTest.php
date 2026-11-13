<?php

namespace Modules\Centro\Tests\Feature\TipoCentro;

use App\Models\Ciudadano;
use App\Models\Version;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centro\Enums\ElementoCatalogo;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\ColeccionPlazas;
use Modules\Centro\Models\ExclusionCatalogo;
use Modules\Centro\Models\Prescripcion;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;
use Modules\Centro\Services\TipoCentro\ExclusionCatalogoService;
use Modules\Centro\Tests\Concerns\TipoCentroTestSetup;
use Modules\Intervencion\Services\PlanDeIntervencionService;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Prestaciones\Services\TiposCentroPrestacionService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo D — Catálogo (TF-TCE-21 a 24 y 26; el 20 y el 25 están en ConfiguracionEfectivaTest).
 *
 * @see docs/instrucciones-cli/2026-10-tipo-centro-tests.md
 */
class CatalogoEfectivoTest extends TestCase
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
     * Ids de las prestaciones efectivas de un centro, ordenados.
     *
     * @param Centro $centro
     *
     * @return list<int>
     */
    private function prestacionesDe($centro): array
    {
        return app(ConfiguracionEfectivaCentro::class)->prestaciones($centro->fresh())->pluck('id')->sort()->values()->all();
    }

    /** TF-TCE-21 — Con la restricción desactivada no se puede excluir. */
    #[Test]
    public function tf_tce_21_restriccion_desactivada(): void
    {
        $this->expectException(DomainException::class);
        app(ExclusionCatalogoService::class)->excluir($this->cssNorte, ElementoCatalogo::Prestacion, $this->p1->id, $this->directoraNorte);
    }

    /** TF-TCE-22 — Con la restricción activada: excluir del catálogo del tipo, y la exclusión sobrevive al desactivarla. */
    #[Test]
    public function tf_tce_22_restriccion_activada(): void
    {
        $this->tipoCss->update(['permite_restriccion_en_centro' => true]);
        $servicio = app(ExclusionCatalogoService::class);

        $servicio->excluir($this->cssNorte->fresh(), ElementoCatalogo::Prestacion, $this->p1->id, $this->directoraNorte);
        $this->assertSame([$this->p2->id], $this->prestacionesDe($this->cssNorte));
        $this->assertSame(collect([$this->p1->id, $this->p2->id])->sort()->values()->all(), $this->prestacionesDe($this->cssSur));

        try {
            $servicio->excluir($this->cssNorte->fresh(), ElementoCatalogo::Prestacion, $this->p3->id, $this->directoraNorte);
            $this->fail('Se esperaba DomainException');
        } catch (DomainException) {
            // esperado: $p3 no está en el catálogo del tipo
        }

        $this->tipoCss->update(['permite_restriccion_en_centro' => false]);
        $this->assertSame(collect([$this->p1->id, $this->p2->id])->sort()->values()->all(), $this->prestacionesDe($this->cssNorte));
        $this->assertTrue(ExclusionCatalogo::where('centro_id', $this->cssNorte->id)->exists());
    }

    /** TF-TCE-23 — Una prestación nueva del tipo llega a todos sus centros; queda versión anterior. */
    #[Test]
    public function tf_tce_23_lo_nuevo_del_tipo_llega_a_todos(): void
    {
        $this->tipoCss->update(['permite_restriccion_en_centro' => true]);
        app(ExclusionCatalogoService::class)->excluir($this->cssNorte->fresh(), ElementoCatalogo::Prestacion, $this->p1->id, $this->directoraNorte);
        $p4 = Prestacion::create(['codigo' => 'P4', 'nombre' => 'Nueva', 'tipo_prestacion' => 'servicio', 'nivel_garantia' => 'garantizada', 'activa' => true]);

        app(TiposCentroPrestacionService::class)->sincronizar($p4, [$this->tipoCss->id], $this->admin);

        $this->assertContains($p4->id, $this->prestacionesDe($this->cssNorte));
        $this->assertContains($p4->id, $this->prestacionesDe($this->cssSur));
        $version = Version::where('versionable_type', $p4->getMorphClass())->where('versionable_id', $p4->id)->latest('id')->first();
        $this->assertNotNull($version);
        $this->assertNotContains($this->tipoCss->id, array_column($version->datos['tipos_centro'], 'id'));
    }

    /** TF-TCE-24 — Solo los tipos de plan del tipo del centro (en el servicio). */
    #[Test]
    public function tf_tce_24_tipos_de_plan_por_tipo_de_centro(): void
    {
        $planes = app(PlanDeIntervencionService::class);

        foreach ([[$this->planPia, $this->cssNorte], [$this->planPiso, $this->ciamVallecas]] as [$tipoPlan, $centro]) {
            try {
                $planes->exigirTipoPlanDelCentro($tipoPlan->id, $centro);
                $this->fail("Se esperaba DomainException para {$tipoPlan->slug} en {$centro->nombre}");
            } catch (DomainException) {
                // esperado
            }
        }

        $planes->exigirTipoPlanDelCentro($this->planPia->id, $this->ciamVallecas);
        $this->assertSame(['PISO'], app(ConfiguracionEfectivaCentro::class)->tiposPlan($this->cssNorte)->pluck('nombre')->all());
    }

    /**
     * TF-TCE-26 — Mover una prestación de tipo no toca lo existente. La prescripción
     * no enlaza con la prestación (apunta a una colección de plazas): se comprueba que
     * una prescripción activa en `$cssNorte` no cambia.
     */
    #[Test]
    public function tf_tce_26_mover_una_prestacion_de_tipo_no_toca_lo_existente(): void
    {
        $coleccion = ColeccionPlazas::create(['centro_id' => $this->cssNorte->id, 'nombre' => 'Plazas', 'tipo_plaza' => 'pernocta', 'modo_acceso' => 'directo', 'capacidad' => 1, 'activa' => true, 'fecha_alta' => '2026-01-01']);
        $prescripcion = Prescripcion::create([
            'profesional_id' => $this->directoraNorte->profesional_id, 'ciudadano_id' => Ciudadano::factory()->create()->id,
            'tipo_destino' => 'coleccion_plazas', 'destino_id' => $coleccion->id, 'estado' => 'activa',
            'fecha_prescripcion' => '2026-10-01',
        ]);
        $antes = $prescripcion->fresh()->toArray();

        app(TiposCentroPrestacionService::class)->sincronizar($this->p2, [$this->tipoCiam->id], $this->admin);

        $this->assertSame($antes, $prescripcion->fresh()->toArray());
        $this->assertNotContains($this->p2->id, $this->prestacionesDe($this->cssNorte));
        $this->assertContains($this->p2->id, $this->prestacionesDe($this->ciamVallecas));
    }
}
