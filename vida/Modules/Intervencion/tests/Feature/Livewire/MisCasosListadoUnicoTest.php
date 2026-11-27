<?php

namespace Modules\Intervencion\Tests\Feature\Livewire;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Http\Livewire\MisCasosPage;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Intervencion\Models\Entrevista;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\SeguimientoPlan;
use Modules\Intervencion\Services\IntervencionSidebarDataService;
use Modules\Intervencion\Tests\Concerns\CircuitoTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * «Mis casos» como listado único (TF-LW-CAS-11 a 17).
 *
 * Una fila por Historia Social, tanto si el profesional es su referencia como si es
 * responsable de un plan en curso (el PIA del CIAM); la columna «Planes» muestra
 * todos los planes en curso de la persona por su tipo y el filtro «Planes» sustituye
 * al de PISO y al de especializados.
 */
class MisCasosListadoUnicoTest extends TestCase
{
    use CircuitoTestSetup;
    use RefreshDatabase;

    /**
     * Escenario del circuito: Ana, con PISO en el CSS Norte (referencia `tsNorte`).
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCircuito();
    }

    /**
     * Historias de la página actual del listado del usuario.
     *
     * @param User $usuario
     * @param array<string, string> $filtros
     *
     * @return list<int>
     */
    private function historiasDe($usuario, array $filtros = []): array
    {
        $componente = Livewire::actingAs($usuario)->test(MisCasosPage::class);
        foreach ($filtros as $propiedad => $valor) {
            $componente->set($propiedad, $valor);
        }

        return collect($componente->get('casos')->items())->pluck('historia_id')->all();
    }

    /**
     * Seguimiento con fecha del siguiente para un plan.
     *
     * @param PlanDeIntervencion $plan
     * @param string $fechaSiguiente
     *
     * @return void
     */
    private function seguimiento(PlanDeIntervencion $plan, string $fechaSiguiente): void
    {
        $entrevista = Entrevista::factory()->seguimiento()->create([
            'historia_id' => $plan->historia_id,
            'profesional_id' => $plan->profesional_responsable_id,
        ]);
        SeguimientoPlan::factory()->create([
            'plan_id' => $plan->id,
            'entrevista_id' => $entrevista->id,
            'profesional_id' => $plan->profesional_responsable_id,
            'fecha_siguiente_seguimiento' => $fechaSiguiente,
        ]);
    }

    /** TF-LW-CAS-11 — Referencia y responsable del PIA de la misma persona: una sola fila con PISO y PIA. [negativo: sin duplicados] */
    #[Test]
    public function referencia_y_responsable_de_un_plan_salen_en_una_sola_fila(): void
    {
        [, $historia] = $this->personaEnCentro($this->ciamVallecas, referencia: $this->psicologaVallecas);
        $piso = $this->planActivo($historia, $this->planPiso, $this->ciamVallecas, $this->psicologaVallecas);
        $this->planActivo($historia, $this->planPia, $this->ciamVallecas, $this->psicologaVallecas, $piso);

        $componente = Livewire::actingAs($this->psicologaVallecas)->test(MisCasosPage::class);

        $this->assertSame([$historia->id], collect($componente->get('casos')->items())->pluck('historia_id')->all());
        $this->assertSame(['PISO', 'PIA'], $componente->get('planesDelPage')->get($historia->id)->map(fn ($p) => $p->tipoPlan->nombre)->values()->all());
        $componente->assertDontSee('Otros planes de los que soy responsable');
    }

    /** TF-LW-CAS-12 — Solo responsable del PIA (la referencia es de otro centro): la historia sale, con todos sus planes. */
    #[Test]
    public function responsable_de_un_plan_ve_la_historia_con_los_planes_de_otros_centros(): void
    {
        $this->planActivo($this->historiaAna, $this->planPia, $this->ciamVallecas, $this->psicologaVallecas, $this->pisoAna);

        $componente = Livewire::actingAs($this->psicologaVallecas)->test(MisCasosPage::class);

        $this->assertSame([$this->historiaAna->id], collect($componente->get('casos')->items())->pluck('historia_id')->all());
        $componente->assertSee('PISO')->assertSee('PIA');

        // La referencia del CSS también ve el PIA del CIAM en su fila.
        $this->assertEqualsCanonicalizing(
            ['PISO', 'PIA'],
            Livewire::actingAs($this->tsNorte)->test(MisCasosPage::class)->get('planesDelPage')->get($this->historiaAna->id)->map(fn ($p) => $p->tipoPlan->nombre)->all(),
        );
    }

    /** TF-LW-CAS-13 — Sin plan en curso, «—»; un plan cerrado ni cuenta ni trae la historia. [negativo] */
    #[Test]
    public function planes_cerrados_no_se_muestran_ni_traen_la_historia(): void
    {
        [, $historia] = $this->personaEnCentro($this->ciamVallecas, referencia: $this->psicologaVallecas);
        [, $ajena] = $this->personaEnCentro($this->ciamVallecas);
        foreach ([$historia, $ajena] as $h) {
            $this->planActivo($h, $this->planPiso, $this->ciamVallecas, $this->psicologaVallecas)->update(['estado' => EstadoPlan::Cerrado]);
        }

        $componente = Livewire::actingAs($this->psicologaVallecas)->test(MisCasosPage::class);

        $this->assertSame([$historia->id], collect($componente->get('casos')->items())->pluck('historia_id')->all());
        $this->assertNull($componente->get('planesDelPage')->get($historia->id));
        $componente->assertSee('—');
    }

    /** TF-LW-CAS-14 — Filtro «Planes»: por tipo de plan y «sin plan en curso». [negativo] */
    #[Test]
    public function filtro_de_planes_por_tipo_y_sin_plan(): void
    {
        [, $sinPlan] = $this->personaEnCentro($this->cssNorte, referencia: $this->tsNorte);
        $this->planActivo($this->historiaAna, $this->planPia, $this->ciamVallecas, $this->psicologaVallecas, $this->pisoAna);

        $this->assertEqualsCanonicalizing([$this->historiaAna->id, $this->historiaMarta->id, $sinPlan->id], $this->historiasDe($this->tsNorte));
        $this->assertSame([$this->historiaAna->id], $this->historiasDe($this->tsNorte, ['filtroPlanes' => (string) $this->planPia->id]));
        $this->assertEqualsCanonicalizing([$this->historiaAna->id, $this->historiaMarta->id], $this->historiasDe($this->tsNorte, ['filtroPlanes' => (string) $this->planPiso->id]));
        $this->assertSame([], $this->historiasDe($this->tsNorte, ['filtroPlanes' => (string) $this->planPsicologico->id]));
        $this->assertSame([$sinPlan->id], $this->historiasDe($this->tsNorte, ['filtroPlanes' => MisCasosPage::SIN_PLAN]));

        Livewire::actingAs($this->tsNorte)->test(MisCasosPage::class)
            ->assertSee('Con PISO')->assertSee('Con PIA')->assertSee('Sin plan en curso');
    }

    /** TF-LW-CAS-15 — El seguimiento de un plan de otro profesional no marca la urgencia del caso. [negativo] */
    #[Test]
    public function el_seguimiento_de_planes_ajenos_no_cuenta(): void
    {
        $pia = $this->planActivo($this->historiaAna, $this->planPia, $this->ciamVallecas, $this->psicologaVallecas, $this->pisoAna);
        $this->seguimiento($pia, today()->subDays(2)->toDateString());
        $this->seguimiento($this->pisoAna, today()->addDays(20)->toDateString());

        $fila = fn ($usuario) => collect(Livewire::actingAs($usuario)->test(MisCasosPage::class)->get('casos')->items())
            ->firstWhere('historia_id', $this->historiaAna->id);

        $this->assertSame(today()->addDays(20)->toDateString(), $fila($this->tsNorte)->fecha_siguiente_seguimiento);
        $this->assertSame(today()->subDays(2)->toDateString(), $fila($this->psicologaVallecas)->fecha_siguiente_seguimiento);
    }

    /** TF-LW-CAS-16 — «Inicio» es la fecha más antigua de las dos vías (referencia o plan). */
    #[Test]
    public function inicio_es_la_fecha_mas_antigua_de_las_dos_vias(): void
    {
        $this->planActivo($this->historiaAna, $this->planPsicologico, $this->cssNorte, $this->tsNorte)
            ->update(['fecha_inicio' => '2025-06-01']);
        AsignacionProfesional::where('historia_id', $this->historiaAna->id)->update(['fecha_inicio' => '2026-01-01']);

        $fila = collect(Livewire::actingAs($this->tsNorte)->test(MisCasosPage::class)->get('casos')->items())
            ->firstWhere('historia_id', $this->historiaAna->id);

        $this->assertSame('2025-06-01', $fila->fecha_inicio);
    }

    /** TF-LW-CAS-17 — El contador del menú cuenta las mismas historias que el listado, cada una una vez. [negativo] */
    #[Test]
    public function el_contador_del_menu_coincide_con_el_listado(): void
    {
        [, $propia] = $this->personaEnCentro($this->ciamVallecas, referencia: $this->psicologaVallecas);
        $piso = $this->planActivo($propia, $this->planPiso, $this->ciamVallecas, $this->psicologaVallecas);
        $this->planActivo($propia, $this->planPia, $this->ciamVallecas, $this->psicologaVallecas, $piso);
        $this->planActivo($this->historiaAna, $this->planPia, $this->ciamVallecas, $this->psicologaVallecas, $this->pisoAna);

        $this->actingAs($this->psicologaVallecas);

        $this->assertSame(2, app(IntervencionSidebarDataService::class)->misCasosCount());
        $this->assertSame(2, Livewire::test(MisCasosPage::class)->get('casos')->total());
    }
}
