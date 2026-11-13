<?php

namespace Modules\Centro\Tests\Feature\TipoCentro;

use App\Livewire\CentroTrabajo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Centro\Tests\Concerns\TipoCentroTestSetup;
use Modules\Supervision\Http\Livewire\EquipoPage;
use Modules\Supervision\Http\Livewire\SelectorCentro;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo I, interfaz — Centro de trabajo en la barra superior y pantallas de
 * supervisión con el centro supervisado (TF-TCE-43 y 44; addenda D1.6 y D1.8).
 *
 * @see docs/instrucciones-cli/2026-10-tipo-centro-tests.md
 */
class CentroTrabajoInterfazTest extends TestCase
{
    use RefreshDatabase;
    use TipoCentroTestSetup;

    /**
     * Escenario común (lunes 2026-11-02) con los perfiles de la psicóloga:
     * lunes y martes en Vallecas, miércoles y jueves en Usera.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarTipoCentro();

        foreach ([[$this->ciamVallecas, ['1', '2']], [$this->ciamUsera, ['3', '4']]] as [$centro, $dias]) {
            PerfilHorarioProfesional::factory()->create([
                'usuario_id' => $this->psicologa->id, 'centro_id' => $centro->id, 'vigente_desde' => '2026-01-01',
                'horario_habitual' => array_fill_keys($dias, [['inicio' => '09:00', 'fin' => '14:00']]),
            ]);
        }
    }

    /** TF-TCE-43 — Barra superior: multicentro con selector, un centro sin él, sin centro la UO. */
    #[Test]
    public function tf_tce_43_barra_superior(): void
    {
        // Multicentro, el lunes: Vallecas, con selector de los dos centros
        $this->actingAs($this->psicologa);
        Livewire::test(CentroTrabajo::class)
            ->assertSee('CIAM · Puente de Vallecas')
            ->assertSee('CIAM · Usera')
            ->assertSeeHtml('dropdown-menu');

        // En una pantalla operativa real, dentro de la barra
        $this->get(route('intervencion.casos.index'))->assertOk()->assertSee('CIAM · Puente de Vallecas');

        // Un solo centro: el mismo texto, sin selector
        $soloVallecas = $this->usuario('solo.vallecas', 'intervencion', $this->uoCiamVallecas);
        $this->actingAs($soloVallecas);
        Livewire::test(CentroTrabajo::class)
            ->assertSee('CIAM · Puente de Vallecas')
            ->assertDontSee('CIAM · Usera')
            ->assertDontSeeHtml('dropdown-menu');

        // Sin centro: el nombre de su UO, sin selector
        $this->actingAs($this->tecnicaArea);
        Livewire::test(CentroTrabajo::class)
            ->assertSee($this->uoArea->nombre)
            ->assertDontSeeHtml('dropdown-menu');
    }

    /** TF-TCE-43 (selector) — Elegir en la barra cambia el centro activo; un centro ajeno no. */
    #[Test]
    public function tf_tce_43_elegir_en_la_barra(): void
    {
        $this->actingAs($this->psicologa);
        $centros = app(CentroDeUsuario::class);

        Livewire::test(CentroTrabajo::class)->call('elegir', $this->ciamUsera->id)->assertRedirect();
        $this->assertSame($this->ciamUsera->id, $centros->centroActivo($this->psicologa)?->id);

        Livewire::test(CentroTrabajo::class)->call('elegir', $this->cssNorte->id)->assertNoRedirect();
        $this->assertSame($this->ciamUsera->id, $centros->centroActivo($this->psicologa)?->id);
    }

    /** TF-TCE-44 — Mi equipo con el centro activo; quien está en los dos centros aparece en ambos. */
    #[Test]
    public function tf_tce_44_mi_equipo_con_el_centro_activo(): void
    {
        $this->conProfesional($this->psicologa, 'Psicóloga Itinerante');
        $soloUsera = $this->usuario('solo.usera', 'intervencion', $this->uoCiamUsera);
        $this->conProfesional($soloUsera, 'Trabajadora Usera');

        $supervisora = $this->usuario('supervisora.ciam', 'supervision', $this->uoCiamVallecas);
        $this->adscribir($supervisora, $this->uoCiamUsera);
        $this->actingAs($supervisora);
        $centros = app(CentroDeUsuario::class);

        $centros->elegir($supervisora, $this->ciamVallecas);
        Livewire::test(EquipoPage::class)
            ->assertSee('Psicóloga Itinerante')
            ->assertDontSee('Trabajadora Usera');

        $centros->elegir($supervisora, $this->ciamUsera);
        Livewire::test(EquipoPage::class)
            ->assertSee('Psicóloga Itinerante')
            ->assertSee('Trabajadora Usera');
    }

    /**
     * TF-TCE-44 (selector de supervisión) — Quien supervisa varios centros por la
     * funcional los elige en el menú; un centro fuera de su ámbito, no. El
     * cuadrante del mes se abre en los que supervisa y no en los demás.
     */
    #[Test]
    public function tf_tce_44_selector_de_supervision(): void
    {
        $this->actingAs($this->coordIgualdad);
        $centros = app(CentroDeUsuario::class);

        // Sin centros propios: por defecto el primero de su ámbito (por nombre)
        $this->assertSame($this->ciamVallecas->id, $centros->centroSupervisado($this->coordIgualdad)?->id);
        Livewire::test(SelectorCentro::class)
            ->assertSee('Puente de Vallecas')
            ->assertSee('Usera')
            ->assertDontSee('Norte')
            ->call('elegir', $this->ciamUsera->id)
            ->assertRedirect();
        $this->assertSame($this->ciamUsera->id, $centros->centroSupervisado($this->coordIgualdad)?->id);

        Livewire::test(SelectorCentro::class)->call('elegir', $this->cssNorte->id)->assertNoRedirect();
        $this->assertSame($this->ciamUsera->id, $centros->centroSupervisado($this->coordIgualdad)?->id);

        $this->get(route('agenda.cuadrante', ['centro' => $this->ciamUsera->id, 'anyo' => 2026, 'mes' => 11]))->assertOk();
        $this->get(route('agenda.cuadrante', ['centro' => $this->cssNorte->id, 'anyo' => 2026, 'mes' => 11]))->assertForbidden();

        // Con un solo centro supervisable no hay selector
        $soloVallecas = $this->usuario('sup.vallecas', 'supervision', $this->uoCiamVallecas);
        $this->actingAs($soloVallecas);
        Livewire::test(SelectorCentro::class)->assertDontSeeHtml('<select');
    }

    /**
     * Ficha de profesional vinculada al usuario, para que aparezca en Mi equipo.
     *
     * @param User $usuario
     * @param string $nombre
     *
     * @return void
     */
    private function conProfesional(User $usuario, string $nombre): void
    {
        [$nombre, $apellido] = explode(' ', $nombre, 2);
        $cargo = Cargo::firstOrCreate(['slug' => 'psicologo'], ['nombre' => 'Psicólogo/a', 'activo' => true]);
        $relacion = TipoRelacionProfesional::firstOrCreate(['nombre' => 'Laboral']);
        $profesional = Profesional::create([
            'nombre' => $nombre, 'apellido1' => $apellido, 'sexo' => 'M', 'cargo_id' => $cargo->id,
            'tipo_relacion_id' => $relacion->id, 'fecha_inicio' => '2020-01-01', 'activo' => true,
        ]);
        $usuario->update(['profesional_id' => $profesional->id]);
    }
}
