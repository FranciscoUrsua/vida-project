<?php

namespace Modules\Ciudadania\Tests\Feature\Livewire;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Atencion\Models\RegistroAtencion;
use Modules\Ciudadania\Http\Livewire\FichaCiudadanoPage;
use Tests\TestCase;

/**
 * Tests funcionales de la UI de atenciones en FichaCiudadanoPage.
 * Nomenclatura: TF-LW-AT-XX
 */
class FichaAtencionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Inicializa permisos y roles necesarios para los tests.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);
    }

    /**
     * Monta el componente con un usuario con el rol indicado.
     *
     * @param string|null $rol Rol a asignar al usuario.
     * @return array{0: \Livewire\Testing\TestableLivewire, 1: Ciudadano, 2: User}
     */
    private function montarFicha(?string $rol = 'intervencion'): array
    {
        $user = User::factory()->create();
        if ($rol) {
            $user->assignRole($rol);
        }
        $this->actingAs($user);

        $ciudadano = Ciudadano::factory()->create();
        $componente = Livewire::test(FichaCiudadanoPage::class, [
            'ciudadano' => $ciudadano->id,
        ]);

        return [$componente, $ciudadano, $user];
    }

    // TF-LW-AT-01: Usuario con consulta_basica ve botón "Nueva atención"
    public function test_consulta_basica_ve_boton_nueva_atencion(): void
    {
        [$componente] = $this->montarFicha('consulta_basica');
        $componente->assertSee('Nueva atención');
    }

    // TF-LW-AT-02: Usuario con consulta_basica NO ve "Abrir historia social"
    public function test_consulta_basica_no_ve_abrir_historia(): void
    {
        [$componente] = $this->montarFicha('consulta_basica');
        $componente->assertDontSee('Abrir historia social');
    }

    // TF-LW-AT-03: Usuario con intervencion ve "Abrir historia social" si no tiene HS
    public function test_intervencion_ve_abrir_historia_sin_hs(): void
    {
        [$componente] = $this->montarFicha('intervencion');
        $componente->assertSee('Abrir historia social');
    }

    // TF-LW-AT-04: Usuario con intervencion ve "Ver historia social" si ya tiene HS
    public function test_intervencion_ve_ver_historia_con_hs(): void
    {
        [$componente, $ciudadano, $user] = $this->montarFicha('intervencion');

        $uo = UnidadOrganizativa::factory()->create();
        HistoriaSocial::factory()->create([
            'ciudadano_id'           => $ciudadano->id,
            'unidad_organizativa_id' => $uo->id,
        ]);

        $componente = Livewire::test(FichaCiudadanoPage::class, [
            'ciudadano' => $ciudadano->id,
        ]);

        $componente
            ->assertSee('Ver historia social')
            ->assertDontSee('Abrir historia social');
    }

    // TF-LW-AT-05: abrirModalAtencion abre el modal
    public function test_abrir_modal_atencion(): void
    {
        [$componente] = $this->montarFicha('intervencion');
        $componente
            ->call('abrirModalAtencion')
            ->assertSet('modalAtencionAbierto', true);
    }

    // TF-LW-AT-06: cerrarModalAtencion cierra el modal
    public function test_cerrar_modal_atencion(): void
    {
        [$componente] = $this->montarFicha('intervencion');
        $componente
            ->call('abrirModalAtencion')
            ->call('cerrarModalAtencion')
            ->assertSet('modalAtencionAbierto', false);
    }

    // TF-LW-AT-07: guardarAtencion crea el registro y cierra el modal
    public function test_guardar_atencion(): void
    {
        [$componente, $ciudadano] = $this->montarFicha('intervencion');

        $componente
            ->call('abrirModalAtencion')
            ->set('atencionFecha', now()->toDateString())
            ->set('atencionDemanda', 'Solicita información sobre el bono social')
            ->set('atencionRespuesta', 'Se le orienta al servicio de tramitación')
            ->call('guardarAtencion')
            ->assertSet('modalAtencionAbierto', false);

        $this->assertDatabaseHas('registros_atencion', [
            'ciudadano_id' => $ciudadano->id,
            'demanda'      => 'Solicita información sobre el bono social',
        ]);
    }

    // TF-LW-AT-08: guardarAtencion con demanda vacía falla validación
    public function test_guardar_atencion_sin_demanda_falla(): void
    {
        [$componente] = $this->montarFicha('intervencion');

        $componente
            ->call('abrirModalAtencion')
            ->set('atencionFecha', now()->toDateString())
            ->set('atencionDemanda', '')
            ->call('guardarAtencion')
            ->assertHasErrors(['atencionDemanda']);
    }

    // TF-LW-AT-09: El historial muestra los registros del ciudadano
    public function test_historial_muestra_registros(): void
    {
        [$componente, $ciudadano, $user] = $this->montarFicha('intervencion');

        RegistroAtencion::factory()->create([
            'ciudadano_id'   => $ciudadano->id,
            'profesional_id' => $user->id,
            'demanda'        => 'Consulta sobre pensiones',
        ]);

        $componente = Livewire::test(FichaCiudadanoPage::class, [
            'ciudadano' => $ciudadano->id,
        ]);

        $componente->assertSee('Consulta sobre pensiones');
    }

    // TF-LW-AT-10: abrirHistoriaSocial crea la HS y redirige
    public function test_abrir_historia_social_crea_hs(): void
    {
        [$componente, $ciudadano, $user] = $this->montarFicha('intervencion');

        $uo = UnidadOrganizativa::factory()->create();
        $user->adscripciones()->create([
            'unidad_organizativa_id' => $uo->id,
            'fecha_inicio'           => now()->toDateString(),
        ]);

        $componente->call('abrirHistoriaSocial');

        $this->assertDatabaseHas('historias_sociales', [
            'ciudadano_id' => $ciudadano->id,
        ]);
    }

    // TF-LW-AT-11: abrirHistoriaSocial no duplica si ya existe HS
    public function test_abrir_historia_social_no_duplica(): void
    {
        [$componente, $ciudadano, $user] = $this->montarFicha('intervencion');

        $uo = UnidadOrganizativa::factory()->create();
        HistoriaSocial::factory()->create([
            'ciudadano_id'           => $ciudadano->id,
            'unidad_organizativa_id' => $uo->id,
        ]);

        $this->assertCount(
            1,
            HistoriaSocial::withoutGlobalScopes()->where('ciudadano_id', $ciudadano->id)->get()
        );
    }
}
