<?php

namespace Modules\Intervencion\Tests\Feature\Livewire;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Carbon\Carbon;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Intervencion\Http\Livewire\AgendaPage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales del componente Livewire AgendaPage.
 *
 * TF-LW-AGE-01 a TF-LW-AGE-14
 *
 * @see docs/instrucciones-cli/ui-intervencion-entrega1.md §6
 */
class AgendaPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Crea un usuario con rol intervencion y UO asignada.
     *
     * @return User
     */
    private function crearUsuarioIntervencion(): User
    {
        $uo = UnidadOrganizativa::create([
            'nombre'    => 'CSS Test Agenda',
            'tipo'      => 'centro',
            'parent_id' => null,
            'activa'    => true,
        ]);

        $usuario = User::create([
            'name'              => 'Técnico Test',
            'email'             => 'tsr-test@vida360.test',
            'password'          => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso'     => false,
        ]);

        $usuario->assignRole('intervencion');

        UsuarioUo::create([
            'usuario_id'             => $usuario->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo'           => 'interno',
            'fecha_inicio'           => today()->toDateString(),
        ]);

        return $usuario;
    }

    /**
     * Crea un usuario sin rol intervencion.
     *
     * @return User
     */
    private function crearUsuarioSinRol(): User
    {
        return User::create([
            'name'              => 'Sin Rol Test',
            'email'             => 'sinrol@vida360.test',
            'password'          => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso'     => false,
        ]);
    }

    // -------------------------------------------------------------------------
    // Tests de rutas
    // -------------------------------------------------------------------------

    /**
     * TF-LW-AGE-01 — Redirige a /intervencion/agenda al acceder a /intervencion.
     */
    #[Test]
    public function redirige_a_agenda_al_acceder_a_intervencion(): void
    {
        $usuario = $this->crearUsuarioIntervencion();

        $this->actingAs($usuario)
            ->get('/intervencion')
            ->assertRedirect('/intervencion/agenda');
    }

    /**
     * TF-LW-AGE-02 — Usuario sin rol intervencion recibe 403 al acceder a /intervencion/agenda.
     */
    #[Test]
    public function usuario_sin_rol_recibe_403(): void
    {
        $usuario = $this->crearUsuarioSinRol();

        $this->actingAs($usuario)
            ->get('/intervencion/agenda')
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Tests del componente Livewire
    // -------------------------------------------------------------------------

    /**
     * TF-LW-AGE-03 — El componente AgendaPage monta correctamente con fechaAncla = today().
     */
    #[Test]
    public function monta_con_fecha_ancla_hoy(): void
    {
        $usuario = $this->crearUsuarioIntervencion();

        Livewire::actingAs($usuario)
            ->test(AgendaPage::class)
            ->assertSet('fechaAncla', today()->toDateString())
            ->assertSet('vista', 'dia');
    }

    /**
     * TF-LW-AGE-04 — navegarSiguiente en vista dia avanza 1 día.
     */
    #[Test]
    public function navegar_siguiente_en_dia_avanza_un_dia(): void
    {
        $usuario = $this->crearUsuarioIntervencion();
        $hoy = today()->toDateString();

        Livewire::actingAs($usuario)
            ->test(AgendaPage::class)
            ->set('vista', 'dia')
            ->call('navegarSiguiente')
            ->assertSet('fechaAncla', today()->addDay()->toDateString());
    }

    /**
     * TF-LW-AGE-05 — navegarAnterior en vista dia retrocede 1 día.
     */
    #[Test]
    public function navegar_anterior_en_dia_retrocede_un_dia(): void
    {
        $usuario = $this->crearUsuarioIntervencion();

        Livewire::actingAs($usuario)
            ->test(AgendaPage::class)
            ->set('vista', 'dia')
            ->call('navegarAnterior')
            ->assertSet('fechaAncla', today()->subDay()->toDateString());
    }

    /**
     * TF-LW-AGE-06 — navegarSiguiente en vista semana avanza 7 días.
     */
    #[Test]
    public function navegar_siguiente_en_semana_avanza_siete_dias(): void
    {
        $usuario = $this->crearUsuarioIntervencion();

        Livewire::actingAs($usuario)
            ->test(AgendaPage::class)
            ->set('vista', 'semana')
            ->call('navegarSiguiente')
            ->assertSet('fechaAncla', today()->addWeek()->toDateString());
    }

    /**
     * TF-LW-AGE-07 — navegarSiguiente en vista mes avanza 1 mes.
     */
    #[Test]
    public function navegar_siguiente_en_mes_avanza_un_mes(): void
    {
        $usuario = $this->crearUsuarioIntervencion();

        Livewire::actingAs($usuario)
            ->test(AgendaPage::class)
            ->set('vista', 'mes')
            ->call('navegarSiguiente')
            ->assertSet('fechaAncla', today()->addMonth()->toDateString());
    }

    /**
     * TF-LW-AGE-08 — irAHoy resetea fechaAncla a today() desde cualquier fecha.
     */
    #[Test]
    public function ir_a_hoy_resetea_fecha_ancla(): void
    {
        $usuario = $this->crearUsuarioIntervencion();
        $futuro = today()->addMonths(3)->toDateString();

        Livewire::actingAs($usuario)
            ->test(AgendaPage::class)
            ->set('fechaAncla', $futuro)
            ->call('irAHoy')
            ->assertSet('fechaAncla', today()->toDateString());
    }

    /**
     * TF-LW-AGE-09 — setVista('semana') cambia la vista y actualiza el título.
     */
    #[Test]
    public function set_vista_semana_cambia_vista_y_titulo(): void
    {
        $usuario = $this->crearUsuarioIntervencion();

        $componente = Livewire::actingAs($usuario)
            ->test(AgendaPage::class)
            ->call('setVista', 'semana')
            ->assertSet('vista', 'semana');

        // El título de semana contiene un guión largo (–) entre las fechas
        $this->assertStringContainsString('–', $componente->get('tituloFecha'));
    }

    /**
     * TF-LW-AGE-10 — La vista renderiza sin errores en los tres modos.
     */
    #[Test]
    public function renderiza_sin_errores_en_los_tres_modos(): void
    {
        $usuario = $this->crearUsuarioIntervencion();

        foreach (['dia', 'semana', 'mes'] as $modo) {
            Livewire::actingAs($usuario)
                ->test(AgendaPage::class)
                ->set('vista', $modo)
                ->assertOk()
                ->assertHasNoErrors();
        }
    }

    /**
     * TF-LW-AGE-11 — El label del tercer KPI dice "Citas hoy" en vista día.
     */
    #[Test]
    public function kpi_citas_label_es_citas_hoy_en_vista_dia(): void
    {
        $usuario = $this->crearUsuarioIntervencion();

        Livewire::actingAs($usuario)
            ->test(AgendaPage::class)
            ->set('vista', 'dia')
            ->assertSee('Citas hoy');
    }

    /**
     * TF-LW-AGE-12 — El label del tercer KPI dice "Citas esta semana" en vista semana.
     */
    #[Test]
    public function kpi_citas_label_es_citas_esta_semana_en_vista_semana(): void
    {
        $usuario = $this->crearUsuarioIntervencion();

        Livewire::actingAs($usuario)
            ->test(AgendaPage::class)
            ->set('vista', 'semana')
            ->assertSee('Citas esta semana');
    }

    /**
     * TF-LW-AGE-13 — El sidebar contiene los cuatro ítems de navegación.
     */
    #[Test]
    public function sidebar_contiene_cuatro_items_de_navegacion(): void
    {
        $usuario = $this->crearUsuarioIntervencion();

        $this->actingAs($usuario)
            ->get('/intervencion/agenda')
            ->assertSee('Agenda')
            ->assertSee('Mis casos')
            ->assertSee('Alertas y mensajes')
            ->assertSee('Buscar ciudadano');
    }

    /**
     * TF-LW-AGE-14 — El ítem "Agenda" tiene clase activa cuando la ruta es intervencion.agenda.*.
     */
    #[Test]
    public function item_agenda_tiene_clase_activo_en_ruta_agenda(): void
    {
        $usuario = $this->crearUsuarioIntervencion();

        $this->actingAs($usuario)
            ->get('/intervencion/agenda')
            ->assertSee('activo');
    }
}
