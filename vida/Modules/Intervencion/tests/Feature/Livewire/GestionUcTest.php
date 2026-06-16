<?php

namespace Modules\Intervencion\Tests\Feature\Livewire;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Ciudadania\Models\UnidadConvivencia;
use Modules\Ciudadania\Models\UnidadConvivenciaMiembro;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales del modal de gestión de UC en CiudadanoPage.
 *
 * TF-LW-UC-01 a TF-LW-UC-13
 *
 * @see docs/instrucciones-cli/uc-modal-ui.md
 */
class GestionUcTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uo;

    private User $usuario;

    private Ciudadano $ciudadano;

    private HistoriaSocial $historia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->uo = UnidadOrganizativa::create([
            'nombre'    => 'CSS Test UC',
            'tipo'      => 'centro',
            'parent_id' => null,
            'activa'    => true,
        ]);

        $this->usuario = User::create([
            'name'               => 'TSR UC Test',
            'email'              => 'uc-test@vida360.test',
            'password'           => 'secreto',
            'email_verified_at'  => now(),
            'primer_acceso'      => false,
        ]);

        $this->usuario->assignRole('intervencion');

        UsuarioUo::create([
            'usuario_id'              => $this->usuario->id,
            'unidad_organizativa_id'  => $this->uo->id,
            'tipo_vinculo'            => 'interno',
            'fecha_inicio'            => today()->toDateString(),
        ]);

        $this->ciudadano = Ciudadano::factory()->create();

        $this->historia = HistoriaSocial::create([
            'ciudadano_id'            => $this->ciudadano->id,
            'unidad_organizativa_id'  => $this->uo->id,
            'ciudadano_protegido'     => false,
            'estado'                  => 'abierta',
        ]);
    }

    /**
     * Monta el componente, opcionalmente añadiendo al ciudadano titular como miembro de una UC.
     */
    private function montarComponente(?UnidadConvivencia $uc = null): \Livewire\Features\SupportTesting\Testable
    {
        if ($uc) {
            $uc->agregarMiembro($this->ciudadano->id);
        }

        return Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia]);
    }

    /**
     * TF-LW-UC-01: abrirModalUc establece modalUcAbierto = true.
     */
    #[Test]
    public function abrir_modal_uc_abre_el_modal(): void
    {
        $this->montarComponente()
            ->call('abrirModalUc')
            ->assertSet('modalUcAbierto', true);
    }

    /**
     * TF-LW-UC-02: cerrarModalUc establece modalUcAbierto = false.
     */
    #[Test]
    public function cerrar_modal_uc_cierra_el_modal(): void
    {
        $this->montarComponente()
            ->call('abrirModalUc')
            ->call('cerrarModalUc')
            ->assertSet('modalUcAbierto', false);
    }

    /**
     * TF-LW-UC-03: crearUc crea la UC y añade al ciudadano titular como miembro.
     */
    #[Test]
    public function crear_uc_crea_la_uc_y_anade_al_titular(): void
    {
        $this->montarComponente()->call('crearUc');

        $this->assertDatabaseCount('unidades_convivencia', 1);
        $this->assertDatabaseCount('unidad_convivencia_miembros', 1);
    }

    /**
     * TF-LW-UC-04: crearUc no crea una segunda UC si ya existe una activa.
     */
    #[Test]
    public function crear_uc_no_duplica_si_ya_existe(): void
    {
        $uc = UnidadConvivencia::factory()->create();

        $this->montarComponente($uc)->call('crearUc');

        $this->assertDatabaseCount('unidades_convivencia', 1);
    }

    /**
     * TF-LW-UC-05: seleccionarCiudadanoUc establece ucCiudadanoSeleccionado.
     */
    #[Test]
    public function seleccionar_ciudadano_uc_establece_la_propiedad(): void
    {
        $uc   = UnidadConvivencia::factory()->create();
        $otro = Ciudadano::factory()->create();

        $this->montarComponente($uc)
            ->call('seleccionarCiudadanoUc', $otro->id)
            ->assertSet('ucCiudadanoSeleccionado', $otro->id);
    }

    /**
     * TF-LW-UC-06: confirmarAnadirMiembro añade el ciudadano a la UC vigente.
     */
    #[Test]
    public function confirmar_anadir_miembro_anade_el_ciudadano(): void
    {
        $uc   = UnidadConvivencia::factory()->create();
        $otro = Ciudadano::factory()->create();

        $this->montarComponente($uc)
            ->call('seleccionarCiudadanoUc', $otro->id)
            ->call('confirmarAnadirMiembro');

        $this->assertDatabaseCount('unidad_convivencia_miembros', 2);
    }

    /**
     * TF-LW-UC-07: confirmarAnadirMiembro limpia ucCiudadanoSeleccionado tras éxito.
     */
    #[Test]
    public function anadir_miembro_limpia_seleccion_tras_exito(): void
    {
        $uc   = UnidadConvivencia::factory()->create();
        $otro = Ciudadano::factory()->create();

        $this->montarComponente($uc)
            ->call('seleccionarCiudadanoUc', $otro->id)
            ->call('confirmarAnadirMiembro')
            ->assertSet('ucCiudadanoSeleccionado', null);
    }

    /**
     * TF-LW-UC-08: iniciarBajaMiembro establece ucMiembroParaBaja con el ID del miembro.
     */
    #[Test]
    public function iniciar_baja_miembro_establece_la_propiedad(): void
    {
        $uc         = UnidadConvivencia::factory()->create();
        $componente = $this->montarComponente($uc);
        $miembroId  = UnidadConvivenciaMiembro::first()->id;

        $componente
            ->call('iniciarBajaMiembro', $miembroId)
            ->assertSet('ucMiembroParaBaja', $miembroId);
    }

    /**
     * TF-LW-UC-09: confirmarBajaMiembro cierra la membresía estableciendo fecha_fin.
     */
    #[Test]
    public function confirmar_baja_miembro_pone_fecha_fin(): void
    {
        $uc         = UnidadConvivencia::factory()->create();
        $componente = $this->montarComponente($uc);
        $miembro    = UnidadConvivenciaMiembro::first();

        $componente
            ->call('iniciarBajaMiembro', $miembro->id)
            ->call('confirmarBajaMiembro');

        $this->assertNotNull($miembro->fresh()->fecha_fin);
    }

    /**
     * TF-LW-UC-10: cancelarBajaMiembro limpia ucMiembroParaBaja.
     */
    #[Test]
    public function cancelar_baja_miembro_limpia_la_propiedad(): void
    {
        $uc         = UnidadConvivencia::factory()->create();
        $componente = $this->montarComponente($uc);
        $miembro    = UnidadConvivenciaMiembro::first();

        $componente
            ->call('iniciarBajaMiembro', $miembro->id)
            ->call('cancelarBajaMiembro')
            ->assertSet('ucMiembroParaBaja', null);
    }

    /**
     * TF-LW-UC-11: verificarMiembro marca el miembro como verificado con el usuario actual.
     */
    #[Test]
    public function verificar_miembro_marca_como_verificado(): void
    {
        $uc         = UnidadConvivencia::factory()->create();
        $componente = $this->montarComponente($uc);
        $miembro    = UnidadConvivenciaMiembro::first();

        $this->assertFalse($miembro->verificado);

        $componente->call('verificarMiembro', $miembro->id);

        $miembro->refresh();
        $this->assertTrue($miembro->verificado);
        $this->assertNotNull($miembro->verificado_en);
        $this->assertEquals($this->usuario->id, $miembro->verificado_por);
    }

    /**
     * TF-LW-UC-12: ucResultadosBusqueda excluye miembros ya activos y al titular.
     */
    #[Test]
    public function busqueda_excluye_miembros_activos_y_titular(): void
    {
        $uc      = UnidadConvivencia::factory()->create();
        $externo = Ciudadano::factory()->create([
            'nombre'    => 'María',
            'apellido1' => 'García',
        ]);

        $componente = $this->montarComponente($uc)->call('abrirModalUc');

        // El externo (aún no miembro) debe aparecer en resultados
        $componente->set('ucBusqueda', 'María');
        $resultados = $componente->instance()->ucResultadosBusqueda;
        $this->assertTrue($resultados->contains('id', $externo->id));

        // El titular ya es miembro — no debe aparecer en resultados de búsqueda
        $resultados = $componente->instance()->ucResultadosBusqueda;
        $this->assertFalse($resultados->contains('id', $this->ciudadano->id));

        // Añadir externo como miembro: ya no debe aparecer en búsqueda
        $uc->agregarMiembro($externo->id);
        $componente->set('ucBusqueda', 'María');
        $resultados = $componente->instance()->ucResultadosBusqueda;
        $this->assertFalse($resultados->contains('id', $externo->id));
    }

    /**
     * TF-LW-UC-13: ucResultadosBusqueda devuelve vacío con menos de 2 caracteres.
     */
    #[Test]
    public function busqueda_con_menos_de_dos_caracteres_devuelve_vacio(): void
    {
        $uc         = UnidadConvivencia::factory()->create();
        $componente = $this->montarComponente($uc)->call('abrirModalUc');

        $componente->set('ucBusqueda', 'a');
        $this->assertTrue($componente->instance()->ucResultadosBusqueda->isEmpty());
    }
}
