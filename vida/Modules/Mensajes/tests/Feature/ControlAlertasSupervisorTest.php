<?php

namespace Modules\Mensajes\Tests\Feature;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\EstadoAlerta;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Enums\TipoReconocimiento;
use Modules\Mensajes\Exceptions\UnauthorizedException;
use Modules\Mensajes\Http\Livewire\ControlAlertasPage;
use Modules\Mensajes\Livewire\BandejaAlertas;
use Modules\Mensajes\Livewire\NuevoAvisoSupervisor;
use Modules\Mensajes\Models\Alerta;
use Modules\Mensajes\Models\AlertaDestinatario;
use Modules\Mensajes\Services\AlertaService;
use Modules\Supervision\Http\Livewire\Sidebar as SidebarSupervision;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Paso 3 del plan de Mensajes: pantalla de control de alertas del supervisor
 * y avisos a su equipo. Decisiones del desarrollador (2026-09-26): el
 * supervisor cierra las partes escaladas con «Cerrar alerta» y no tiene plazo
 * para hacerlo. TF-MSG-SUP-01 a 16.
 */
class ControlAlertasSupervisorTest extends TestCase
{
    use RefreshDatabase;

    private AlertaService $servicio;

    private UnidadOrganizativa $uo;

    private UnidadOrganizativa $otraUo;

    private User $supervisor;

    private User $ts1;

    private User $ts2;

    private User $ajeno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->servicio = app(AlertaService::class);
        $this->uo = UnidadOrganizativa::create(['nombre' => 'CSS Control', 'tipo' => 'centro', 'activa' => true]);
        $this->otraUo = UnidadOrganizativa::create(['nombre' => 'CSS Ajeno', 'tipo' => 'centro', 'activa' => true]);

        $this->supervisor = $this->crearUsuario('sup@vida360.test', 'supervision', $this->uo);
        $this->ts1 = $this->crearUsuario('ts1@vida360.test', 'intervencion', $this->uo);
        $this->ts2 = $this->crearUsuario('ts2@vida360.test', 'intervencion', $this->uo);
        $this->ajeno = $this->crearUsuario('ajeno@vida360.test', 'intervencion', $this->otraUo);
    }

    /**
     * Crea un usuario con un rol y adscripción vigente a una UO.
     */
    private function crearUsuario(string $email, string $rol, UnidadOrganizativa $uo): User
    {
        $usuario = User::create([
            'name' => $email,
            'email' => $email,
            'password' => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso' => false,
        ]);
        $usuario->assignRole($rol);

        UsuarioUo::create([
            'usuario_id' => $usuario->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today()->subYear()->toDateString(),
        ]);

        return $usuario;
    }

    /**
     * Crea una alerta directa a un miembro del equipo y la escala al supervisor.
     */
    private function alertaEscalada(User $para, string $titulo = 'Alerta escalada'): AlertaDestinatario
    {
        $alerta = $this->servicio->crear([
            'tipo' => TipoAlerta::Alerta,
            'origen_type' => User::class,
            'origen_id' => $para->id,
            'titulo' => $titulo,
            'cuerpo' => 'Cuerpo',
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $para->id,
        ]);
        $this->servicio->escalar($alerta);

        return $alerta->destinatarios()->sole();
    }

    // -------------------------------------------------------------------------
    // Avisos del supervisor
    // -------------------------------------------------------------------------

    /** TF-MSG-SUP-01 — El aviso del supervisor llega a todo su equipo, a nadie más y no a él mismo. */
    #[Test]
    public function aviso_llega_a_todo_el_equipo(): void
    {
        $aviso = $this->servicio->crearAvisoSupervisor($this->supervisor, $this->uo, 'Reunión', 'El jueves a las 10:00');

        $this->assertSame(TipoAlerta::Aviso, $aviso->tipo);
        $this->assertSame(DestinatarioType::Uo, $aviso->destinatario_type);
        $this->assertSame(AlertaService::ORIGEN_SUPERVISOR, $aviso->origen_type);
        $this->assertSame($this->supervisor->id, $aviso->origen_id);
        $this->assertNull($aviso->expira_en);
        $this->assertEqualsCanonicalizing(
            [$this->ts1->id, $this->ts2->id],
            $aviso->destinatarios()->pluck('usuario_id')->all()
        );
    }

    /** TF-MSG-SUP-02 — Quien no es supervisor no puede enviar avisos. */
    #[Test]
    public function sin_rol_supervision_no_envia_avisos(): void
    {
        try {
            $this->servicio->crearAvisoSupervisor($this->ts1, $this->uo, 'Aviso', 'Cuerpo');
            $this->fail('Se esperaba UnauthorizedException');
        } catch (UnauthorizedException) {
            // Rechazo esperado.
        }

        $this->assertSame(0, Alerta::count());
    }

    /** TF-MSG-SUP-03 — Un supervisor no puede enviar avisos a una UO que no es la suya. */
    #[Test]
    public function supervisor_no_envia_a_uo_ajena(): void
    {
        $this->expectException(UnauthorizedException::class);

        $this->servicio->crearAvisoSupervisor($this->supervisor, $this->otraUo, 'Aviso', 'Cuerpo');
    }

    /** TF-MSG-SUP-04 — El miembro del equipo lo ve en Avisos con la etiqueta del supervisor. */
    #[Test]
    public function el_equipo_lo_ve_con_etiqueta(): void
    {
        $this->servicio->crearAvisoSupervisor($this->supervisor, $this->uo, 'Reunión de equipo', 'Cuerpo');

        Livewire::actingAs($this->ts1)
            ->test(BandejaAlertas::class, ['tipo' => 'aviso'])
            ->assertSee('Reunión de equipo')
            ->assertSee('Aviso del supervisor');

        Livewire::actingAs($this->ajeno)
            ->test(BandejaAlertas::class, ['tipo' => 'aviso'])
            ->assertDontSee('Reunión de equipo');
    }

    /** TF-MSG-SUP-05 — El formulario envía el aviso a la UO del supervisor. */
    #[Test]
    public function formulario_envia_aviso(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(NuevoAvisoSupervisor::class)
            ->set('titulo', 'Cierre del centro')
            ->set('cuerpo', 'El viernes el centro cierra a las 14:00.')
            ->call('enviar')
            ->assertHasNoErrors()
            ->assertSet('titulo', '');

        $aviso = Alerta::sole();
        $this->assertSame($this->uo->id, $aviso->destinatario_uo_id);
        $this->assertSame(2, $aviso->destinatarios()->count());
    }

    /** TF-MSG-SUP-06 — El formulario exige título y cuerpo. */
    #[Test]
    public function formulario_exige_titulo_y_cuerpo(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(NuevoAvisoSupervisor::class)
            ->call('enviar')
            ->assertHasErrors(['titulo', 'cuerpo']);

        $this->assertSame(0, Alerta::count());
    }

    /** TF-MSG-SUP-07 — El formulario no permite elegir una UO ajena. */
    #[Test]
    public function formulario_no_admite_uo_ajena(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(NuevoAvisoSupervisor::class)
            ->set('uoId', $this->otraUo->id)
            ->set('titulo', 'Aviso')
            ->set('cuerpo', 'Cuerpo')
            ->call('enviar')
            ->assertHasErrors(['uoId']);

        $this->assertSame(0, Alerta::count());
    }

    /** TF-MSG-SUP-08 — Sin rol de supervisión el formulario no se abre. */
    #[Test]
    public function formulario_exige_rol_supervision(): void
    {
        Livewire::actingAs($this->ts1)
            ->test(NuevoAvisoSupervisor::class)
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Alertas escaladas
    // -------------------------------------------------------------------------

    /** TF-MSG-SUP-09 — «Cerrar alerta» cierra la parte escalada y deja el evento. */
    #[Test]
    public function cerrar_parte_escalada(): void
    {
        $parte = $this->alertaEscalada($this->ts1);

        $this->servicio->cerrarEscalada($parte, $this->supervisor, '10.0.0.9');

        $parte->refresh();
        $this->assertSame(EstadoAlerta::Reconocida, $parte->estado);
        $this->assertNotNull($parte->atendida_en);
        $this->assertSame(EstadoAlerta::Reconocida, $parte->alerta->estado);
        $this->assertDatabaseHas('alerta_reconocimientos', [
            'alerta_destinatario_id' => $parte->id,
            'usuario_id' => $this->supervisor->id,
            'tipo' => TipoReconocimiento::Cerrada->value,
            'ip_address' => '10.0.0.9',
        ]);
    }

    /** TF-MSG-SUP-10 — Solo cierra la parte el supervisor al que se escaló. */
    #[Test]
    public function otro_supervisor_no_cierra(): void
    {
        $parte = $this->alertaEscalada($this->ts1);
        $otroSupervisor = $this->crearUsuario('sup2@vida360.test', 'supervision', $this->otraUo);

        $this->expectException(LogicException::class);

        $this->servicio->cerrarEscalada($parte, $otroSupervisor, '10.0.0.9');
    }

    /** TF-MSG-SUP-11 — No se cierra lo que no está escalado. */
    #[Test]
    public function no_se_cierra_lo_no_escalado(): void
    {
        $alerta = $this->servicio->crear([
            'tipo' => TipoAlerta::Alerta,
            'origen_type' => User::class,
            'origen_id' => $this->ts1->id,
            'titulo' => 'Pendiente',
            'cuerpo' => 'Cuerpo',
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $this->ts1->id,
        ]);

        $this->expectException(LogicException::class);

        $this->servicio->cerrarEscalada($alerta->destinatarios()->sole(), $this->supervisor, '10.0.0.9');
    }

    /** TF-MSG-SUP-12 — El supervisor no tiene plazo: lo escalado no vence aunque se vuelva a escalar. */
    #[Test]
    public function lo_escalado_no_vence(): void
    {
        $parte = $this->alertaEscalada($this->ts1);

        $this->servicio->escalar($parte->alerta);

        $this->assertSame(EstadoAlerta::Escalada, $parte->fresh()->estado);
        $this->assertSame(EstadoAlerta::Escalada, $parte->alerta->fresh()->estado);
    }

    // -------------------------------------------------------------------------
    // Pantalla de control
    // -------------------------------------------------------------------------

    /** TF-MSG-SUP-13 — La pantalla solo es para supervisión. */
    #[Test]
    public function pantalla_solo_para_supervision(): void
    {
        $this->actingAs($this->supervisor)->get(route('supervision.control-alertas'))->assertOk();
        $this->actingAs($this->ts1)->get(route('supervision.control-alertas'))->assertForbidden();
    }

    /** TF-MSG-SUP-14 — Desde la pantalla se cierra una escalada, con confirmación. */
    #[Test]
    public function pantalla_cierra_escalada_con_confirmacion(): void
    {
        $parte = $this->alertaEscalada($this->ts1, 'Visita urgente');

        Livewire::actingAs($this->supervisor)
            ->test(ControlAlertasPage::class)
            ->assertSee('Visita urgente')
            ->call('confirmarCierre', $parte->id)
            ->call('cerrar')
            ->assertDontSee('Visita urgente');

        $this->assertSame(EstadoAlerta::Reconocida, $parte->fresh()->estado);
    }

    /** TF-MSG-SUP-15 — La pantalla no cierra partes escaladas a otro supervisor. */
    #[Test]
    public function pantalla_no_cierra_escalada_ajena(): void
    {
        $parte = $this->alertaEscalada($this->ts1);
        $otroSupervisor = $this->crearUsuario('sup3@vida360.test', 'supervision', $this->otraUo);

        Livewire::actingAs($otroSupervisor)
            ->test(ControlAlertasPage::class)
            ->call('confirmarCierre', $parte->id)
            ->call('cerrar')
            ->assertForbidden();

        $this->assertSame(EstadoAlerta::Escalada, $parte->fresh()->estado);
    }

    /** TF-MSG-SUP-16 — La pantalla muestra cómo van las alertas del equipo, no las de otras UO; el menú cuenta las escaladas. */
    #[Test]
    public function pantalla_muestra_alertas_del_equipo_y_menu_cuenta_escaladas(): void
    {
        $this->servicio->crearAvisoSupervisor($this->supervisor, $this->uo, 'Aviso del equipo', 'Cuerpo');
        $this->servicio->crear([
            'tipo' => TipoAlerta::Alerta,
            'origen_type' => User::class,
            'origen_id' => $this->ajeno->id,
            'titulo' => 'Alerta de otra UO',
            'cuerpo' => 'Cuerpo',
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $this->ajeno->id,
        ]);
        $this->alertaEscalada($this->ts2);

        $pagina = Livewire::actingAs($this->supervisor)->test(ControlAlertasPage::class);
        $equipo = $pagina->instance()->alertasEquipo;

        $this->assertTrue($equipo->contains('titulo', 'Aviso del equipo'));
        $this->assertFalse($equipo->contains('titulo', 'Alerta de otra UO'));
        $pagina->assertSee('0 de 2');

        Livewire::actingAs($this->supervisor)
            ->test(SidebarSupervision::class)
            ->assertSee(route('supervision.control-alertas'));
        $this->assertSame(1, AlertaDestinatario::escaladasA($this->supervisor)->count());
    }
}
