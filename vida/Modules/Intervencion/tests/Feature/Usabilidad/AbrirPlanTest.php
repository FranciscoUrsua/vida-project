<?php

namespace Modules\Intervencion\Tests\Feature\Usabilidad;

use App\Models\HistoriaSocial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Centro\Models\Centro;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use Modules\Intervencion\Http\Livewire\PlanPage;
use Modules\Intervencion\Services\Planes\TiposPlanAbribles;
use Modules\Intervencion\Tests\Concerns\CircuitoTestSetup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * «+ Abrir plan» en la Historia Social (U1, TF-USA-01 a 04): el botón solo aparece si hay algún
 * tipo de plan abrible para la persona en el centro activo, con la misma regla que la pantalla
 * de creación (`TiposPlanAbribles`).
 *
 * Datos: el CSS Norte ofrece PISO (principal) y, en el circuito, dos planes ASP; Ana tiene PISO
 * vigente. Los tests quitan o añaden tipos al CSS para construir cada caso.
 *
 * @see docs/instrucciones-cli/2026-11-usabilidad-historia-social.md, paso 1
 */
class AbrirPlanTest extends TestCase
{
    use CircuitoTestSetup;
    use RefreshDatabase;

    /**
     * Circuito de intervención; el TS del CSS Norte actúa.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCircuito();
        $this->actingAs($this->tsNorte);
    }

    /**
     * El CSS Norte solo ofrece PISO (sin los planes ASP del circuito).
     *
     * @return void
     */
    private function soloPiso(): void
    {
        $this->tipoCss->tiposPlan()->detach([$this->planPsicologico->id, $this->planSocioeducativo->id]);
    }

    /**
     * TF-USA-01 [negativo] — Con el plan principal vigente y ningún otro tipo abrible, sin botón.
     *
     * @return void
     */
    #[Test]
    public function con_plan_vigente_y_nada_mas_que_abrir_no_aparece_el_boton(): void
    {
        $this->soloPiso();

        Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna])
            ->assertSet('puedeAbrirPlan', false)
            ->assertDontSee('Abrir plan');
    }

    /**
     * TF-USA-02 — Sin planes, en el centro de su historia, aparece el botón.
     *
     * @return void
     */
    #[Test]
    public function sin_planes_aparece_el_boton(): void
    {
        $this->soloPiso();
        [, $historia] = $this->personaEnCentro($this->cssNorte, referencia: $this->tsNorte);

        Livewire::test(CiudadanoPage::class, ['historia' => $historia])
            ->assertSee('Abrir plan');
    }

    /**
     * TF-USA-03 — Con el principal vigente y un especializado con entrada directa en el centro,
     * aparece el botón y la creación solo ofrece ese tipo.
     *
     * @return void
     */
    #[Test]
    public function otro_tipo_abrible_muestra_el_boton_y_solo_ofrece_ese_tipo(): void
    {
        $this->soloPiso();
        $this->planPia->update(['admite_entrada_directa' => true]);
        $this->tipoCss->tiposPlan()->attach($this->planPia->id);

        Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna])
            ->assertSee('Abrir plan');

        $tipos = Livewire::withQueryParams(['historia' => $this->historiaAna->id])
            ->test(PlanPage::class)
            ->get('tiposPlanes');

        $this->assertSame([$this->planPia->id => 'PIA'], $tipos);
    }

    /**
     * TF-USA-04 — La Historia Social y la pantalla de creación aplican la misma regla.
     *
     * @param string $caso Caso que monta el escenario.
     *
     * @return void
     */
    #[Test]
    #[DataProvider('casos')]
    public function el_servicio_y_la_pantalla_de_creacion_coinciden(string $caso): void
    {
        $historia = $this->historiaAna;
        $centro = $this->cssNorte;

        match ($caso) {
            'piso_vigente_con_asp' => null,
            'piso_vigente_solo_piso' => $this->soloPiso(),
            'sin_planes' => [, $historia] = $this->personaEnCentro($this->cssNorte, referencia: $this->tsNorte),
            'especializado_sin_entrada_directa' => $this->tipoCss->tiposPlan()->attach($this->planPia->id),
            'historia_de_otro_centro' => [$historia, $centro] = $this->historiaDeOtroCentro(),
        };

        $servicio = app(TiposPlanAbribles::class)->para($historia->fresh(), $centro)->pluck('nombre', 'id')->toArray();
        $pantalla = Livewire::withQueryParams(['historia' => $historia->id])->test(PlanPage::class)->get('tiposPlanes');

        $this->assertEquals($servicio, $pantalla);
    }

    /**
     * Casos de TF-USA-04.
     *
     * @return array<string, array{0: string}>
     */
    public static function casos(): array
    {
        return [
            'PISO vigente y planes ASP' => ['piso_vigente_con_asp'],
            'PISO vigente y nada más' => ['piso_vigente_solo_piso'],
            'Persona sin planes' => ['sin_planes'],
            'Especializado sin entrada directa' => ['especializado_sin_entrada_directa'],
            'Historia de otro centro' => ['historia_de_otro_centro'],
        ];
    }

    /**
     * Historia del CSS Sur vista desde el CSS Norte: el principal no se abre fuera del centro
     * de la historia.
     *
     * @return array{0: HistoriaSocial, 1: Centro}
     */
    private function historiaDeOtroCentro(): array
    {
        [, $historia] = $this->personaEnCentro($this->cssSur);

        return [$historia, $this->cssNorte];
    }
}
