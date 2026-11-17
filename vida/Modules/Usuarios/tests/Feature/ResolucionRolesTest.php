<?php

namespace Modules\Usuarios\Tests\Feature;

use App\Filament\Widgets\RolesPendientesWidget;
use App\Models\User;
use Database\Seeders\ConfiguracionRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Centro\Tests\Concerns\TipoCentroTestSetup;
use Modules\Supervision\Http\Livewire\AprobacionesPage;
use Modules\Usuarios\Models\UsuarioRol;
use Modules\Usuarios\Services\AsignacionRolesService;
use Modules\Usuarios\Services\ResolucionRolesService;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Quién resuelve una solicitud de rol con aprobación previa (§2.8; incidencia del 17 nov
 * 2026: el coordinador de un CSS se quedaba pendiente sin nadie que pudiera aprobarlo).
 *
 * La supervisión con la UO del usuario en su ámbito (también por la jerarquía funcional);
 * si no hay ninguna, `adm_sistema`. Nadie la suya; `adm_sistema` tampoco la que asignó.
 */
class ResolucionRolesTest extends TestCase
{
    use RefreshDatabase;
    use TipoCentroTestSetup;

    private User $coordCss;

    private UsuarioRol $solicitud;

    private User $admUsuarios;

    /**
     * Escenario de Tipo de Centro y la solicitud de `supervision` del coordinador del CSS
     * Norte, hecha por un `adm_usuarios`.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarTipoCentro();
        $this->seed(ConfiguracionRolesSeeder::class);

        $this->admUsuarios = $this->usuario('adm.usuarios', 'adm_usuarios', $this->uoArea);
        $this->coordCss = $this->usuario('coord.css', 'intervencion', $this->uoCssNorte);
        $this->solicitud = app(AsignacionRolesService::class)->asignar($this->coordCss, Role::findByName('supervision'), $this->admUsuarios);
    }

    /**
     * La supervisión de una UO superior (Atención Primaria, por el tipo del centro) aprueba.
     *
     * @return void
     */
    #[Test]
    public function la_supervision_de_una_uo_superior_aprueba(): void
    {
        $this->assertSame('pendiente_aprobacion', $this->solicitud->estado);
        // La dirección del propio centro y la supervisión de la UO superior por el tipo
        $this->assertEqualsCanonicalizing([$this->directoraNorte->id, $this->coordPrimaria->id], app(ResolucionRolesService::class)->supervisoresCompetentes($this->solicitud)->pluck('id')->all());

        Livewire::actingAs($this->coordPrimaria)->test(AprobacionesPage::class)
            ->call('aprobarSolicitud', $this->solicitud->id);

        $this->assertSame('activo', $this->solicitud->fresh()->estado);
        $this->assertTrue($this->coordCss->fresh()->hasRole('supervision'));
    }

    /**
     * Con supervisor competente, `adm_sistema` no la resuelve. [negativo]
     *
     * @return void
     */
    #[Test]
    public function con_supervisor_competente_adm_sistema_no_resuelve(): void
    {
        $servicio = app(ResolucionRolesService::class);
        $this->assertFalse($servicio->puedeResolver($this->admin, $this->solicitud));

        $this->expectException(AuthorizationException::class);
        $servicio->aprobar($this->solicitud, $this->admin);
    }

    /**
     * Sin ningún supervisor competente, la resuelve `adm_sistema` desde el escritorio.
     *
     * @return void
     */
    #[Test]
    public function sin_supervisor_competente_la_resuelve_adm_sistema(): void
    {
        $this->sinSupervisores();

        $this->assertTrue(app(ResolucionRolesService::class)->supervisoresCompetentes($this->solicitud)->isEmpty());

        Livewire::actingAs($this->admin)->test(RolesPendientesWidget::class)
            ->assertSee('adm_sistema (no hay supervisor competente)')
            ->assertTableActionVisible('aprobar', $this->solicitud)
            ->callTableAction('aprobar', $this->solicitud);

        $this->assertSame('activo', $this->solicitud->fresh()->estado);
        $this->assertTrue($this->coordCss->fresh()->hasRole('supervision'));
    }

    /**
     * `adm_sistema` deniega con motivo y el usuario no obtiene el rol.
     *
     * @return void
     */
    #[Test]
    public function adm_sistema_deniega_con_motivo(): void
    {
        $this->sinSupervisores();

        Livewire::actingAs($this->admin)->test(RolesPendientesWidget::class)
            ->callTableAction('denegar', $this->solicitud, ['motivo' => 'No es coordinador del centro']);

        $this->assertSame('denegado', $this->solicitud->fresh()->estado);
        $this->assertFalse($this->coordCss->fresh()->hasRole('supervision'));
    }

    /**
     * `adm_sistema` no aprueba una asignación que ha hecho él. [negativo]
     *
     * @return void
     */
    #[Test]
    public function adm_sistema_no_aprueba_lo_que_asigno(): void
    {
        $this->sinSupervisores();
        $propia = app(AsignacionRolesService::class)->asignar($this->tecnicaArea, Role::findByName('supervision'), $this->admin);

        $this->assertFalse(app(ResolucionRolesService::class)->puedeResolver($this->admin, $propia));

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('No puedes aprobar una asignación que has hecho tú.');
        app(ResolucionRolesService::class)->aprobar($propia, $this->admin);
    }

    /**
     * `adm_usuarios` ya no aprueba desde el escritorio (antes aprobaba cualquiera, también
     * la que acababa de asignar). [negativo]
     *
     * @return void
     */
    #[Test]
    public function adm_usuarios_no_aprueba_desde_el_escritorio(): void
    {
        $this->sinSupervisores();

        Livewire::actingAs($this->admUsuarios)->test(RolesPendientesWidget::class)
            ->assertSee($this->coordCss->email)
            ->assertTableActionHidden('aprobar', $this->solicitud)
            ->assertTableActionHidden('denegar', $this->solicitud);

        $this->assertSame('pendiente_aprobacion', $this->solicitud->fresh()->estado);
    }

    /**
     * Nadie resuelve su propia solicitud, tampoco `adm_sistema`. [negativo]
     *
     * @return void
     */
    #[Test]
    public function nadie_resuelve_la_suya(): void
    {
        $this->admin->syncRoles(['adm_sistema', 'supervision']);
        $suya = app(AsignacionRolesService::class)->asignar($this->admin, Role::findByName('adm_usuarios'), $this->admUsuarios);
        $suya->update(['estado' => 'pendiente_aprobacion']);

        $this->assertFalse(app(ResolucionRolesService::class)->puedeResolver($this->admin, $suya));
    }

    /**
     * Quita `supervision` a quienes la tienen sobre el CSS Norte, como la rama de
     * Atención Primaria de staging.
     *
     * @return void
     */
    private function sinSupervisores(): void
    {
        $this->coordPrimaria->syncRoles([]);
        $this->directoraNorte->syncRoles([]);
    }
}
