<?php

namespace Modules\Intervencion\Tests\Feature\Asignacion;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Centro\Tests\Concerns\AsignacionTestSetup;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Enums\OrigenAsignacionReferencia;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Services\Asignacion\ActividadCasosService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo H: actividad de los casos (TF-ASG-33).
 *
 * @see docs/instrucciones-cli/2026-09-asignacion-tests.md
 */
class ActividadCasosTest extends TestCase
{
    use AsignacionTestSetup;
    use RefreshDatabase;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->montarEscenarioAsignacion();
    }

    /**
     * Crea una historia en CSS Norte con el profesional como referencia vigente.
     *
     * @param User $profesional
     * @param string $nombre Nombre del ciudadano.
     * @return HistoriaSocial
     */
    private function casoDe(User $profesional, string $nombre): HistoriaSocial
    {
        $historia = $this->historiaDe($this->crearCiudadano($nombre));

        AsignacionProfesional::create([
            'historia_id' => $historia->id,
            'profesional_id' => $profesional->id,
            'centro_id' => $this->cssNorte->id,
            'origen' => OrigenAsignacionReferencia::Sorteo,
            'cuenta_en_reparto' => true,
            'fecha_inicio' => '2026-01-15',
        ]);

        return $historia;
    }

    /**
     * @param HistoriaSocial $historia
     * @param string $fecha
     * @return void
     */
    private function apunte(HistoriaSocial $historia, string $fecha): void
    {
        Apunte::factory()->create([
            'historia_id' => $historia->id,
            'autor_id' => $this->ts1->id,
            'fecha' => $fecha,
        ]);
    }

    /**
     * @param HistoriaSocial $historia
     * @param EstadoPlan $estado
     * @return void
     */
    private function plan(HistoriaSocial $historia, EstadoPlan $estado = EstadoPlan::Activo): void
    {
        PlanDeIntervencion::factory()->create([
            'historia_id' => $historia->id,
            'profesional_responsable_id' => $this->ts1->id,
            'estado' => $estado,
        ]);
    }

    /**
     * @return ActividadCasosService
     */
    private function servicio(): ActividadCasosService
    {
        return app(ActividadCasosService::class);
    }

    /**
     * Fila del resumen de un profesional.
     *
     * @param User $profesional
     * @return array{profesional: User, asignados: int, con_actividad: int, dormidos: int}|null
     */
    private function filaDe(User $profesional): ?array
    {
        return $this->servicio()->resumen($this->cssNorte)
            ->first(fn (array $fila) => $fila['profesional']->id === $profesional->id);
    }

    /**
     * TF-ASG-33 — Plan activo o apunte reciente es actividad; el último apunte
     * de hace 8 meses sin plan es un caso dormido. El resumen no toca nada.
     *
     * @return void
     */
    #[Test]
    public function distingue_casos_con_actividad_y_dormidos_sin_alterar_asignaciones(): void
    {
        // Dado: plan activo sin apuntes, apunte de hace 2 meses, último apunte de hace 8 meses
        $conPlan = $this->casoDe($this->ts1, 'Con plan');
        $this->plan($conPlan);

        $reciente = $this->casoDe($this->ts1, 'Apunte reciente');
        $this->apunte($reciente, now()->subMonths(2)->toDateString());

        $dormida = $this->casoDe($this->ts1, 'Dormida');
        $this->apunte($dormida, now()->subMonths(8)->toDateString());

        $antes = DB::table('asignaciones_profesional')->orderBy('id')->get()->toArray();

        // Cuando
        $fila = $this->filaDe($this->ts1);

        // Entonces
        $this->assertSame(6, $this->cssNorte->meses_inactividad_caso);
        $this->assertSame(3, $fila['asignados']);
        $this->assertSame(2, $fila['con_actividad']);
        $this->assertSame(1, $fila['dormidos']);

        $this->assertTrue($this->servicio()->conActividad($conPlan, $this->cssNorte));
        $this->assertTrue($this->servicio()->conActividad($reciente, $this->cssNorte));
        $this->assertFalse($this->servicio()->conActividad($dormida, $this->cssNorte));

        $this->assertEquals($antes, DB::table('asignaciones_profesional')->orderBy('id')->get()->toArray());
    }

    /**
     * Un plan cerrado no es actividad y el umbral es el del centro: con
     * 12 meses de inactividad, el apunte de hace 8 meses vuelve a contar.
     *
     * @return void
     */
    #[Test]
    public function el_plan_cerrado_no_cuenta_y_el_umbral_es_el_del_centro(): void
    {
        $cerrado = $this->casoDe($this->ts1, 'Plan cerrado');
        $this->plan($cerrado, EstadoPlan::Cerrado);

        $antiguo = $this->casoDe($this->ts1, 'Apunte antiguo');
        $this->apunte($antiguo, now()->subMonths(8)->toDateString());

        $this->assertSame(0, $this->filaDe($this->ts1)['con_actividad']);

        $this->cssNorte->update(['meses_inactividad_caso' => 12]);

        $fila = $this->filaDe($this->ts1);
        $this->assertSame(1, $fila['con_actividad']);
        $this->assertSame(1, $fila['dormidos']);
    }

    /**
     * Solo cuentan las referencias vigentes del centro: las cerradas y las de
     * otro centro no suman.
     *
     * @return void
     */
    #[Test]
    public function solo_cuenta_las_referencias_vigentes_del_centro(): void
    {
        $this->casoDe($this->ts1, 'Vigente');

        $cerrada = $this->casoDe($this->ts1, 'Cerrada');
        AsignacionProfesional::where('historia_id', $cerrada->id)->update(['fecha_fin' => today()]);

        $deOtroCentro = $this->historiaDe($this->crearCiudadano('Sur'), $this->cssSur);
        AsignacionProfesional::create([
            'historia_id' => $deOtroCentro->id,
            'profesional_id' => $this->ts1->id,
            'centro_id' => $this->cssSur->id,
            'origen' => OrigenAsignacionReferencia::Manual,
            'cuenta_en_reparto' => false,
            'motivo' => 'Prueba',
            'fecha_inicio' => '2026-01-15',
        ]);

        $this->assertSame(1, $this->filaDe($this->ts1)['asignados']);
        $this->assertNull($this->filaDe($this->ts2));
    }

    /**
     * El resumen hace un número fijo de consultas, sea cual sea el número de casos.
     *
     * @return void
     */
    #[Test]
    public function el_resumen_no_hace_una_consulta_por_caso(): void
    {
        $this->casoDe($this->ts1, 'Uno');
        $this->casoDe($this->ts2, 'Dos');

        $consultasCon = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->servicio()->resumen($this->cssNorte);
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $pocas = $consultasCon();

        for ($i = 0; $i < 10; $i++) {
            $this->apunte($this->casoDe($i % 2 ? $this->ts1 : $this->ts3, "Más {$i}"), today()->toDateString());
        }

        $this->assertSame($pocas, $consultasCon());
    }

    /**
     * Un profesional dado de baja (soft delete) con casos vigentes sigue en el
     * resumen, marcado como borrado: son justo los casos que hay que repartir
     * (RN-08). Antes rompía la pantalla con «Undefined array key».
     *
     * @return void
     */
    #[Test]
    public function el_profesional_dado_de_baja_con_casos_sigue_en_el_resumen(): void
    {
        $this->casoDe($this->ts1, 'De quien se fue');
        $this->ts1->delete();

        $fila = $this->filaDe($this->ts1);

        $this->assertNotNull($fila);
        $this->assertSame(1, $fila['asignados']);
        $this->assertTrue($fila['profesional']->trashed());
    }
}
