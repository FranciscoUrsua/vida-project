<?php

namespace Modules\Mensajes\Tests\Feature;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\EstadoAlerta;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Livewire\BandejaAlertas;
use Modules\Mensajes\Models\Alerta;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo 9: Componente Livewire BandejaAlertas.
 *
 * @see docs/modulo-mensajes.md §9
 */
class BandejaAlertasTest extends TestCase
{
    use RefreshDatabase;

    private function crearAlertaDirecta(User $usuario, array $overrides = []): Alerta
    {
        return Alerta::create(array_merge([
            'tipo' => TipoAlerta::Alerta,
            'origen_type' => 'App\\Models\\User',
            'origen_id' => $usuario->id,
            'titulo' => 'Alerta de prueba',
            'cuerpo' => 'Cuerpo de la alerta',
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $usuario->id,
            'estado' => EstadoAlerta::Pendiente,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * T-LW-01 — El usuario solo ve sus propias alertas.
     */
    #[Test]
    public function t_lw_01_el_usuario_solo_ve_sus_propias_alertas(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();

        $alertaA = $this->crearAlertaDirecta($usuarioA, ['titulo' => 'Alerta de A']);
        $alertaB = $this->crearAlertaDirecta($usuarioB, ['titulo' => 'Alerta de B']);

        Livewire::actingAs($usuarioA)
            ->test(BandejaAlertas::class)
            ->assertSee('Alerta de A')
            ->assertDontSee('Alerta de B');
    }

    /**
     * T-LW-02 — El usuario ve las alertas dirigidas a su rol en su UO.
     */
    #[Test]
    public function t_lw_02_usuario_ve_alertas_dirigidas_a_su_rol_en_su_uo(): void
    {
        $uo = UnidadOrganizativa::create(['nombre' => 'UO LW-02', 'tipo' => 'servicio', 'activa' => true]);
        $usuario = User::factory()->create();

        UsuarioUo::create([
            'usuario_id' => $usuario->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo' => 'adscripcion',
            'fecha_inicio' => now()->toDateString(),
        ]);

        Role::firstOrCreate(['name' => 'trabajador_social', 'guard_name' => 'web']);
        $usuario->assignRole('trabajador_social');

        $alertaRol = Alerta::create([
            'tipo' => TipoAlerta::Aviso,
            'origen_type' => 'App\\Models\\User',
            'origen_id' => $usuario->id,
            'titulo' => 'Aviso para rol UO',
            'cuerpo' => 'Cuerpo',
            'destinatario_type' => DestinatarioType::RolUo,
            'destinatario_rol' => 'trabajador_social',
            'destinatario_uo_id' => $uo->id,
            'estado' => EstadoAlerta::Pendiente,
        ]);

        Livewire::actingAs($usuario)
            ->test(BandejaAlertas::class)
            ->assertSee('Aviso para rol UO');
    }

    /**
     * T-LW-03 — Reconocer una alerta desde la bandeja actualiza su estado.
     */
    #[Test]
    public function t_lw_03_reconocer_alerta_desde_bandeja_actualiza_estado(): void
    {
        $usuario = User::factory()->create();
        $alerta = $this->crearAlertaDirecta($usuario, ['titulo' => 'Alerta reconocible']);

        Livewire::actingAs($usuario)
            ->test(BandejaAlertas::class)
            ->assertSee('Alerta reconocible')
            ->call('confirmarReconocimiento', $alerta->id)
            ->call('reconocer')
            ->assertDontSee('Alerta reconocible');

        $this->assertEquals(EstadoAlerta::Reconocida, $alerta->fresh()->estado);
    }

    /**
     * T-LW-04 — Un usuario no puede reconocer una alerta que no le pertenece.
     */
    #[Test]
    public function t_lw_04_usuario_no_puede_reconocer_alerta_ajena(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();

        $alertaDeA = $this->crearAlertaDirecta($usuarioA, ['titulo' => 'Alerta de A']);

        Livewire::actingAs($usuarioB)
            ->test(BandejaAlertas::class)
            ->set('alertaConfirmandoId', $alertaDeA->id)
            ->call('reconocer')
            ->assertForbidden();

        $this->assertEquals(EstadoAlerta::Pendiente, $alertaDeA->fresh()->estado);
    }

    /**
     * T-LW-05 — Las alertas se ordenan por urgencia: alertas con vencimiento antes que avisos.
     */
    #[Test]
    public function t_lw_05_alertas_ordenadas_por_urgencia(): void
    {
        $usuario = User::factory()->create();

        Alerta::create([
            'tipo' => TipoAlerta::Aviso,
            'origen_type' => 'App\\Models\\User',
            'origen_id' => $usuario->id,
            'titulo' => 'AVISO SIN VTO',
            'cuerpo' => 'Cuerpo',
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $usuario->id,
            'estado' => EstadoAlerta::Pendiente,
        ]);

        Alerta::create([
            'tipo' => TipoAlerta::Alerta,
            'origen_type' => 'App\\Models\\User',
            'origen_id' => $usuario->id,
            'titulo' => 'ALERTA 3H',
            'cuerpo' => 'Cuerpo',
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $usuario->id,
            'estado' => EstadoAlerta::Pendiente,
            'expira_en' => now()->addHours(3),
        ]);

        Alerta::create([
            'tipo' => TipoAlerta::Alerta,
            'origen_type' => 'App\\Models\\User',
            'origen_id' => $usuario->id,
            'titulo' => 'ALERTA 1H',
            'cuerpo' => 'Cuerpo',
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $usuario->id,
            'estado' => EstadoAlerta::Pendiente,
            'expira_en' => now()->addHour(),
        ]);

        Livewire::actingAs($usuario)
            ->test(BandejaAlertas::class)
            ->assertSeeInOrder(['ALERTA 1H', 'ALERTA 3H', 'AVISO SIN VTO']);
    }
}
