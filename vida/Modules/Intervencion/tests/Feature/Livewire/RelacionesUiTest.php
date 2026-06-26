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
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Ciudadania\Database\Seeders\TipoRelacionSeeder;
use Modules\Ciudadania\Models\CiudadanoRelacion;
use Modules\Ciudadania\Models\UnidadConvivencia;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales de la UI de relaciones en CiudadanoPage.
 *
 * TF-LW-REL-01 a TF-LW-REL-12
 *
 * @see docs/instrucciones-cli/relaciones-ui-intervencion.md
 */
class RelacionesUiTest extends TestCase
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
        $this->seed(TipoRelacionSeeder::class);

        $this->uo = UnidadOrganizativa::create([
            'nombre' => 'CSS Test Relaciones',
            'tipo' => 'centro',
            'parent_id' => null,
            'activa' => true,
        ]);

        $this->usuario = User::create([
            'name' => 'TSR Relaciones Test',
            'email' => 'rel-test@vida360.test',
            'password' => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso' => false,
        ]);

        $this->usuario->assignRole('intervencion');

        UsuarioUo::create([
            'usuario_id' => $this->usuario->id,
            'unidad_organizativa_id' => $this->uo->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today()->toDateString(),
        ]);

        $this->ciudadano = Ciudadano::factory()->create();

        $this->historia = HistoriaSocial::create([
            'ciudadano_id' => $this->ciudadano->id,
            'unidad_organizativa_id' => $this->uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);
    }

    /**
     * @return Testable<CiudadanoPage>
     */
    private function montar(): Testable
    {
        return Livewire::actingAs($this->usuario)
            ->test(CiudadanoPage::class, ['historia' => $this->historia]);
    }

    /**
     * TF-LW-REL-01: Sin representante, la línea no aparece en el renderizado.
     */
    #[Test]
    public function sin_representante_no_muestra_linea(): void
    {
        $this->montar()->assertDontSee('hs-representante');
    }

    /**
     * TF-LW-REL-02: Con representante activo, la línea muestra su apellido.
     */
    #[Test]
    public function con_representante_activo_muestra_nombre(): void
    {
        $representante = Ciudadano::factory()->create([
            'apellido1' => 'LopezRepresentante',
        ]);
        CiudadanoRelacion::create([
            'ciudadano_id' => $this->ciudadano->id,
            'ciudadano_relacionado_id' => $representante->id,
            'tipo_relacion' => 'representante',
            'fecha_inicio' => now()->toDateString(),
        ]);

        $this->montar()->assertSee('LopezRepresentante');
    }

    /**
     * TF-LW-REL-03: abrirModalRepresentante establece modalRepresentanteAbierto = true.
     */
    #[Test]
    public function abrir_modal_representante_abre_el_modal(): void
    {
        $this->montar()
            ->call('abrirModalRepresentante')
            ->assertSet('modalRepresentanteAbierto', true);
    }

    /**
     * TF-LW-REL-04: Modal representante muestra teléfono cuando existe.
     */
    #[Test]
    public function modal_representante_muestra_telefono(): void
    {
        $representante = Ciudadano::factory()->create(['telefono' => '612345678']);
        CiudadanoRelacion::create([
            'ciudadano_id' => $this->ciudadano->id,
            'ciudadano_relacionado_id' => $representante->id,
            'tipo_relacion' => 'representante',
            'fecha_inicio' => now()->toDateString(),
        ]);

        $this->montar()
            ->call('abrirModalRepresentante')
            ->assertSee('612345678');
    }

    /**
     * TF-LW-REL-05: cerrarModalRepresentante establece modalRepresentanteAbierto = false.
     */
    #[Test]
    public function cerrar_modal_representante_cierra_el_modal(): void
    {
        $this->montar()
            ->call('abrirModalRepresentante')
            ->call('cerrarModalRepresentante')
            ->assertSet('modalRepresentanteAbierto', false);
    }

    /**
     * TF-LW-REL-06: abrirModalRelaciones establece modalRelacionesAbierto = true.
     */
    #[Test]
    public function abrir_modal_relaciones_abre_el_modal(): void
    {
        $this->montar()
            ->call('abrirModalRelaciones')
            ->assertSet('modalRelacionesAbierto', true);
    }

    /**
     * TF-LW-REL-07: cerrarModalRelaciones establece modalRelacionesAbierto = false.
     */
    #[Test]
    public function cerrar_modal_relaciones_cierra_el_modal(): void
    {
        $this->montar()
            ->call('abrirModalRelaciones')
            ->call('cerrarModalRelaciones')
            ->assertSet('modalRelacionesAbierto', false);
    }

    /**
     * TF-LW-REL-08: relacionesAgrupadas agrupa correctamente por tipo.
     */
    #[Test]
    public function relaciones_agrupadas_agrupa_por_tipo(): void
    {
        $hijo1 = Ciudadano::factory()->create();
        $hijo2 = Ciudadano::factory()->create();
        $conyuge = Ciudadano::factory()->create();

        foreach ([$hijo1, $hijo2] as $hijo) {
            CiudadanoRelacion::create([
                'ciudadano_id' => $this->ciudadano->id,
                'ciudadano_relacionado_id' => $hijo->id,
                'tipo_relacion' => 'hijo',
                'fecha_inicio' => now()->toDateString(),
            ]);
        }

        CiudadanoRelacion::create([
            'ciudadano_id' => $this->ciudadano->id,
            'ciudadano_relacionado_id' => $conyuge->id,
            'tipo_relacion' => 'conyuge',
            'fecha_inicio' => now()->toDateString(),
        ]);

        $comp = $this->montar();
        $agrupadas = $comp->get('relacionesAgrupadas');

        $this->assertArrayHasKey('hijo', $agrupadas->toArray());
        $this->assertArrayHasKey('conyuge', $agrupadas->toArray());
        $this->assertCount(2, $agrupadas['hijo']['miembros']);
        $this->assertCount(1, $agrupadas['conyuge']['miembros']);
    }

    /**
     * TF-LW-REL-09: relacionesAgrupadas excluye relaciones con fecha_fin.
     */
    #[Test]
    public function relaciones_agrupadas_excluye_cerradas(): void
    {
        $exConyuge = Ciudadano::factory()->create();
        CiudadanoRelacion::create([
            'ciudadano_id' => $this->ciudadano->id,
            'ciudadano_relacionado_id' => $exConyuge->id,
            'tipo_relacion' => 'conyuge',
            'fecha_inicio' => '2020-01-01',
            'fecha_fin' => '2023-06-01',
        ]);

        $agrupadas = $this->montar()->get('relacionesAgrupadas');

        $this->assertArrayNotHasKey('conyuge', $agrupadas->toArray());
    }

    /**
     * TF-LW-REL-10: relacionesMiembrosUc devuelve la etiqueta correcta para un miembro.
     */
    #[Test]
    public function relaciones_miembros_uc_tipo_correcto(): void
    {
        $hijo = Ciudadano::factory()->create();

        $uc = UnidadConvivencia::create([
            'domicilio' => 'Calle Test 1',
            'fecha_constitucion' => now()->toDateString(),
        ]);
        $uc->agregarMiembro($this->ciudadano->id);
        $uc->agregarMiembro($hijo->id);

        CiudadanoRelacion::create([
            'ciudadano_id' => $this->ciudadano->id,
            'ciudadano_relacionado_id' => $hijo->id,
            'tipo_relacion' => 'hijo',
            'fecha_inicio' => now()->toDateString(),
        ]);

        $relaciones = $this->montar()->get('relacionesMiembrosUc');

        $this->assertTrue(
            $relaciones->contains('Hijo/a'),
            'Se esperaba la etiqueta "Hijo/a" en relacionesMiembrosUc.'
        );
    }

    /**
     * TF-LW-REL-11: Representante con fecha_fin no aparece como representante activo.
     */
    #[Test]
    public function representante_cerrado_no_aparece(): void
    {
        $exRep = Ciudadano::factory()->create(['apellido1' => 'ExRepAnterior']);
        CiudadanoRelacion::create([
            'ciudadano_id' => $this->ciudadano->id,
            'ciudadano_relacionado_id' => $exRep->id,
            'tipo_relacion' => 'representante',
            'fecha_inicio' => '2020-01-01',
            'fecha_fin' => '2023-01-01',
        ]);

        $comp = $this->montar();

        $this->assertNull($comp->get('representante'));
        $comp->assertDontSee('ExRepAnterior');
    }

    /**
     * TF-LW-REL-12: Modal de relaciones vacío muestra enlace a la ficha del ciudadano.
     */
    #[Test]
    public function modal_relaciones_vacio_muestra_enlace_ficha(): void
    {
        $this->montar()
            ->call('abrirModalRelaciones')
            ->assertSee('Gestionar relaciones en la ficha del ciudadano');
    }
}
