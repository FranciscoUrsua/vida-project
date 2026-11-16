<?php

namespace Modules\Ciudadania\Tests\Feature\ConsultaBasica;

use App\Models\Ciudadano;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Ciudadania\Database\Seeders\TipoRelacionSeeder;
use Modules\Ciudadania\Http\Livewire\FichaCiudadanoPage;
use Modules\Ciudadania\Models\UnidadConvivencia;
use Modules\Ciudadania\Tests\Concerns\ConsultaBasicaTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Ficha del ciudadano para consulta básica y tramitación (fase 4, grupo A).
 *
 * TF-CBA-01, 04, 05, 11 y 12.
 *
 * @see docs/instrucciones-cli/2026-11-fase4-consulta-basica.md
 */
class FichaPorRolTest extends TestCase
{
    use ConsultaBasicaTestSetup;
    use RefreshDatabase;

    /**
     * Prepara el escenario y una unidad de convivencia de Ana con Pedro.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarConsultaBasica();
        $this->seed(TipoRelacionSeeder::class);

        $uc = UnidadConvivencia::create(['domicilio' => 'Calle Norte 1', 'fecha_constitucion' => '2026-01-01']);
        $uc->agregarMiembro($this->ana->id);
        $uc->agregarMiembro($this->pedro->id);
    }

    /**
     * Comprueba los bloques visibles y ocultos de la ficha de Ana para un usuario.
     *
     * @param User $usuario
     *
     * @return void
     */
    private function assertBloquesDeAtencionAdministrativa(User $usuario): void
    {
        $this->actingAs($usuario)
            ->get(route('ciudadania.ciudadano.ficha', $this->ana->id))
            ->assertOk()
            ->assertSee('Identificación y contacto')
            ->assertSee('Unidad de convivencia')
            ->assertSee('Relaciones')
            ->assertSee('id="ficha-estado-historia"', false)
            ->assertSee('id="ficha-citas"', false)
            ->assertSee('id="ficha-documentos"', false)
            // Prestaciones, situación social y accesos recientes no se muestran
            ->assertDontSee('Otras prestaciones')
            ->assertDontSee('Ayuda a domicilio de Ana')
            ->assertDontSee('Situación social')
            ->assertDontSee('Accesos recientes');
    }

    /** TF-CBA-01 — Bloques visibles para consulta básica. */
    #[Test]
    public function tf_cba_01_bloques_visibles_para_consulta_basica(): void
    {
        $this->assertBloquesDeAtencionAdministrativa($this->ventanillaNorte);

        // Contraste: intervención sí ve las prestaciones
        $this->actingAs($this->tsNorte)
            ->get(route('ciudadania.ciudadano.ficha', $this->ana->id))
            ->assertSee('Ayuda a domicilio de Ana');
    }

    /** TF-CBA-04 — Consulta fuera de su UO, operación dentro. */
    #[Test]
    public function tf_cba_04_consulta_fuera_de_su_uo_y_operacion_dentro(): void
    {
        $this->actingAs($this->ventanillaSur)
            ->get(route('ciudadania.ciudadano.ficha', $this->ana->id))
            ->assertOk()
            ->assertSee($this->ana->nombre);

        $ficha = Livewire::actingAs($this->ventanillaSur)->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ana->id])
            ->assertSet('puedeEditar', false)
            ->assertSet('puedeEditarRelaciones', false);

        $ficha->set('telefono', '600000001')->call('guardar')->assertForbidden();

        Livewire::actingAs($this->ventanillaSur)->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ana->id])
            ->set('relacionCiudadanoSeleccionado', $this->pedro->id)
            ->set('relacionTipo', 'conyuge')
            ->set('relacionFechaInicio', '2026-11-02')
            ->call('guardarRelacion')
            ->assertForbidden();

        $this->assertNotSame('+34600000001', Ciudadano::withoutGlobalScopes()->find($this->ana->id)->telefono);
    }

    /** TF-CBA-05 — Editar la Capa 1 en su UO: guardado y auditado. */
    #[Test]
    public function tf_cba_05_editar_capa_1_en_su_uo(): void
    {
        Livewire::actingAs($this->ventanillaNorte)->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ana->id])
            ->assertSet('puedeEditar', true)
            ->call('activarEdicion')
            ->set('sexo', 'F') // la factoría no usa las claves del catálogo `ciudadano.sexo`
            ->set('telefono', '600111222')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('+34600111222', Ciudadano::withoutGlobalScopes()->find($this->ana->id)->telefono);
        $this->assertDatabaseHas('audits', [
            'user_id' => $this->ventanillaNorte->id,
            'auditable_type' => (new Ciudadano)->getMorphClass(),
            'auditable_id' => $this->ana->id,
            'accion' => 'editar',
        ]);
    }

    /** TF-CBA-11 — Tramitación ve lo mismo en la ficha. */
    #[Test]
    public function tf_cba_11_tramitacion_ve_lo_mismo_en_la_ficha(): void
    {
        $this->assertBloquesDeAtencionAdministrativa($this->adminNorte);
    }

    /** TF-CBA-12 — El bloque de la historia lo decide el permiso, no el rol. */
    #[Test]
    public function tf_cba_12_decisiones_por_permiso(): void
    {
        Role::findByName('consulta_basica')->revokePermissionTo('historia.ver_estado');
        $this->ventanillaNorte->refresh();

        $this->actingAs($this->ventanillaNorte)
            ->get(route('ciudadania.ciudadano.ficha', $this->ana->id))
            ->assertOk()
            ->assertDontSee('id="ficha-estado-historia"', false);

        // Dárselo al usuario (no por el rol) lo vuelve a mostrar
        $this->ventanillaNorte->givePermissionTo('historia.ver_estado');
        $this->ventanillaNorte->refresh();

        $this->actingAs($this->ventanillaNorte)
            ->get(route('ciudadania.ciudadano.ficha', $this->ana->id))
            ->assertOk()
            ->assertSee('id="ficha-estado-historia"', false);
    }
}
