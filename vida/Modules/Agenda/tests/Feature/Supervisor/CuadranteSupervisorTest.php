<?php

namespace Modules\Agenda\Tests\Feature\Supervisor;

use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Livewire\Supervisor\CuadranteSupervisorPage;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\ExcepcionProfesional;
use Modules\Agenda\Models\LineaCuadrante;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Agenda\Services\SlotMaterializadorService;
use Modules\Centro\Models\Centro;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Services\CuadrantePublicadorService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales TF-AGS-01 a TF-AGS-07 — Cuadrante mensual del supervisor.
 */
class CuadranteSupervisorTest extends TestCase
{
    use RefreshDatabase;
    use AgendaSupervisorTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);
        $this->construirFixturesSupervisor();
    }

    /**
     * TF-AGS-01 — El cuadrante en borrador es visible para el supervisor.
     */
    #[Test]
    public function cuadrante_borrador_es_visible_para_el_supervisor(): void
    {
        // Dado: cuadrante en borrador con líneas para los tres profesionales (setUp)
        // Cuando: el supervisor accede a CuadranteSupervisorPage
        $component = Livewire::actingAs($this->supervisor)
            ->test(CuadranteSupervisorPage::class);

        // Entonces: la vista muestra el estado borrador
        $component->assertOk()->assertSee('Borrador');

        // Y: hay al menos una fila por profesional del centro
        $this->assertCount(3, $component->get('profesionales'));
    }

    /**
     * TF-AGS-02 — Las franjas de tipo atención se muestran con las horas correctas.
     */
    #[Test]
    public function franjas_atencion_muestran_horas_correctas(): void
    {
        // Dado: cuadrante con línea de profesional1 con franja atencion 09:30-14:00
        // (la línea ya existe por crearLineaCuadrante en el setUp del trait)

        // Cuando: se renderiza el cuadrante
        $component = Livewire::actingAs($this->supervisor)
            ->test(CuadranteSupervisorPage::class);

        // Entonces: la vista contiene el texto de la franja horaria
        $component->assertSee('09:30');
        $component->assertSee('14:00');
    }

    /**
     * TF-AGS-03 — Un profesional de otro centro no aparece en el cuadrante.
     */
    #[Test]
    public function profesional_de_otro_centro_no_aparece_en_el_cuadrante(): void
    {
        // Dado: un profesional con perfil en otro centro
        $otroCentro = Centro::create([
            'nombre'       => 'Otro Centro',
            'tipo_gestion' => 'municipal_directo',
            'fecha_alta'   => now()->toDateString(),
        ]);

        $profOtro = $this->crearUsuarioConRol('intervencion', 'otro.centro@vida360.test', $this->uoSupervisor);
        PerfilHorarioProfesional::create([
            'usuario_id'           => $profOtro->id,
            'centro_id'            => $otroCentro->id,
            'jornada_semanal_horas'=> 35,
            'horario_habitual'     => [],
            'vigente_desde'        => '2026-01-01',
            'activo'               => true,
        ]);

        // Cuando: el supervisor ve su cuadrante
        $component = Livewire::actingAs($this->supervisor)
            ->test(CuadranteSupervisorPage::class);

        // Entonces: solo aparecen los 3 profesionales del centro del supervisor
        $this->assertCount(3, $component->get('profesionales'));
    }

    /**
     * TF-AGS-04 — Una línea anulada por excepción se muestra como ausencia.
     */
    #[Test]
    public function linea_anulada_se_muestra_como_ausencia(): void
    {
        // Dado: una excepción de baja médica para profesional1
        $excepcion = ExcepcionProfesional::create([
            'usuario_id'            => $this->profesional1->id,
            'centro_id'             => $this->centro->id,
            'tipo'                  => 'baja_medica',
            'fecha_inicio'          => now()->toDateString(),
            'fecha_fin'             => now()->toDateString(),
            'afecta_disponibilidad' => true,
            'origen'                => 'manual',
            'creado_por_id'         => $this->supervisor->id,
        ]);

        // Y: la línea del cuadrante de esa fecha está anulada
        LineaCuadrante::where('cuadrante_mes_id', $this->cuadrante->id)
            ->where('usuario_id', $this->profesional1->id)
            ->where('fecha', now()->toDateString())
            ->update(['anulada' => true, 'excepcion_id' => $excepcion->id]);

        // Cuando: se renderiza el cuadrante
        $component = Livewire::actingAs($this->supervisor)
            ->test(CuadranteSupervisorPage::class);

        // Entonces: aparece el indicador de ausencia
        $component->assertSee('Ausencia');
    }

    /**
     * TF-AGS-05 — El supervisor puede publicar un cuadrante en borrador.
     */
    #[Test]
    public function supervisor_puede_publicar_cuadrante_borrador(): void
    {
        // Dado: cuadrante en borrador con TipoSlot
        $this->crearTipoSlot();

        // Cuando: el supervisor llama a publicar
        Livewire::actingAs($this->supervisor)
            ->test(CuadranteSupervisorPage::class)
            ->call('publicar');

        // Entonces: el cuadrante queda publicado
        $this->assertEquals(
            EstadoCuadrante::Publicado,
            $this->cuadrante->fresh()->estado
        );
    }

    /**
     * TF-AGS-06 — Solo hay un cuadrante por centro y mes: publicar otra vez el ya
     * publicado no hace nada ni vuelve a materializar slots.
     */
    #[Test]
    public function publicar_dos_veces_no_duplica_slots(): void
    {
        // Dado: el borrador del mes con líneas y un tipo de slot del horario
        $this->crearTipoSlot();
        $this->crearLineaCuadrante($this->profesional1);

        $pagina = Livewire::actingAs($this->supervisor)->test(CuadranteSupervisorPage::class);
        $pagina->call('publicar');
        $slots = Slot::count();

        // Cuando: se vuelve a publicar
        app(CuadrantePublicadorService::class)->publicar($this->cuadrante->fresh(), $this->supervisor->id);

        // Entonces: sigue publicado, con los mismos slots
        $this->assertEquals(EstadoCuadrante::Publicado, $this->cuadrante->fresh()->estado);
        $this->assertGreaterThan(0, $slots);
        $this->assertSame($slots, Slot::count());
    }

    /**
     * TF-AGS-07 — En modo básico el cuadrante no muestra el botón de publicar.
     */
    #[Test]
    public function modo_basico_no_muestra_boton_publicar(): void
    {
        // Dado: el horario del centro está en modo básico
        $this->horario->update(['modo_agenda' => 'basico']);

        // Cuando: el supervisor accede al cuadrante
        $component = Livewire::actingAs($this->supervisor)
            ->test(CuadranteSupervisorPage::class);

        // Entonces: modoManual es false y no hay botón de publicar
        $this->assertFalse($component->get('modoManual'));
        $component->assertDontSee('Publicar cuadrante');
    }
}
