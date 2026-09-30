<?php

namespace Modules\Intervencion\Tests\Feature\Asignacion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agenda\Models\ExcepcionProfesional;
use Modules\Centro\Tests\Concerns\AsignacionTestSetup;
use Modules\Intervencion\Services\Asignacion\PoolReferenciaService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo D: profesionales del reparto (TF-ASG-16, TF-ASG-17).
 *
 * @see docs/instrucciones-cli/2026-09-asignacion-tests.md
 */
class PoolReferenciaTest extends TestCase
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
     * Pesos de los elegibles por id de usuario.
     *
     * @return array<int, float>
     */
    private function elegibles(): array
    {
        return app(PoolReferenciaService::class)->elegibles($this->cssNorte, today())
            ->mapWithKeys(fn (array $e) => [$e['usuario']->id => $e['peso']])
            ->all();
    }

    /**
     * TF-ASG-16 — Solo cargos elegibles con perfil activo, con su jornada como peso.
     *
     * @return void
     */
    #[Test]
    public function solo_entran_cargos_elegibles_con_perfil_activo(): void
    {
        $this->assertSame([
            $this->ts1->id => 35.0,
            $this->ts2->id => 35.0,
            $this->ts3->id => 17.5,
        ], $this->elegibles());
        $this->assertArrayNotHasKey($this->educador->id, $this->elegibles());
    }

    /**
     * TF-ASG-17 — Una ausencia larga excluye del reparto; una corta no.
     *
     * @return void
     */
    #[Test]
    public function la_ausencia_larga_excluye_y_la_corta_no(): void
    {
        foreach ([[$this->ts1, 'baja_medica', 30], [$this->ts2, 'vacaciones', 3]] as [$usuario, $tipo, $dias]) {
            ExcepcionProfesional::create([
                'usuario_id' => $usuario->id,
                'centro_id' => $this->cssNorte->id,
                'tipo' => $tipo,
                'fecha_inicio' => today()->toDateString(),
                'fecha_fin' => today()->addDays($dias - 1)->toDateString(),
                'afecta_disponibilidad' => true,
                'origen' => 'manual',
                'creado_por_id' => $this->supervisor->id,
            ]);
        }

        $elegibles = $this->elegibles();

        $this->assertArrayNotHasKey($this->ts1->id, $elegibles);
        $this->assertArrayHasKey($this->ts2->id, $elegibles);
        $this->assertArrayHasKey($this->ts3->id, $elegibles);
    }
}
