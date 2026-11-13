<?php

namespace Modules\Centro\Tests\Feature\TipoCentro;

use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\TipoCentro\CentroService;
use Modules\Centro\Services\TipoCentro\TipoCentroService;
use Modules\Centro\Tests\Concerns\TipoCentroTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo B — Centro y tipo (TF-TCE-07 a 10). TF-TCE-11 está en ConfiguracionEfectivaTest.
 *
 * @see docs/instrucciones-cli/2026-10-tipo-centro-tests.md
 */
class CentroTipoTest extends TestCase
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

    /** TF-TCE-07 — Un centro sin tipo se rechaza. */
    #[Test]
    public function tf_tce_07_tipo_obligatorio(): void
    {
        $this->expectException(DomainException::class);

        Centro::create(['nombre' => 'Sin tipo', 'tipo_gestion' => 'municipal_directo', 'fecha_alta' => '2026-01-01']);
    }

    /** TF-TCE-08 — El tipo de un centro no cambia (modelo). */
    #[Test]
    public function tf_tce_08_tipo_inmutable(): void
    {
        try {
            $this->cssNorte->update(['tipo_centro_id' => $this->tipoCiam->id]);
            $this->fail('Se esperaba LogicException');
        } catch (LogicException) {
            // esperado
        }

        $this->assertSame($this->tipoCss->id, $this->cssNorte->fresh()->tipo_centro_id);
    }

    /** TF-TCE-09 — No se da de alta un centro en un tipo de baja. */
    #[Test]
    public function tf_tce_09_alta_en_tipo_de_baja(): void
    {
        app(TipoCentroService::class)->darDeBaja($this->tipoVacio, today(), $this->admin);

        $this->expectException(DomainException::class);
        $this->centro('Nuevo', 'Nuevo', $this->tipoVacio->fresh(), $this->uo('UO Nuevo', 'centro', $this->uoJuntaNorte));
    }

    /** TF-TCE-10 — Sustitución: baja del antiguo y alta del nuevo con antecesor. */
    #[Test]
    public function tf_tce_10_sustitucion_por_baja_y_alta(): void
    {
        $servicio = app(CentroService::class);
        $servicio->darDeBaja($this->cssSur, today(), $this->admin);
        $ciamSur = $servicio->darDeAlta([
            'nombre' => 'CIAM Sur',
            'tipo_gestion' => 'municipal_directo',
            'unidad_organizativa_id' => $this->uo('CIAM Sur', 'centro', $this->uoJuntaNorte)->id,
        ], $this->tipoCiam, $this->cssSur, $this->admin);

        $sur = Centro::find($this->cssSur->id);
        $this->assertNotNull($sur);
        $this->assertFalse($sur->activo);
        $this->assertSame($this->cssSur->id, $ciamSur->antecesor->id);
        $this->assertSame($this->tipoCiam->id, $ciamSur->tipo_centro_id);
        $this->assertSame($this->tipoCss->id, $sur->tipo_centro_id);
    }
}
