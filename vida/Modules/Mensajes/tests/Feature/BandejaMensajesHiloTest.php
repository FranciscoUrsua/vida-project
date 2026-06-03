<?php

namespace Modules\Mensajes\Tests\Feature;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Mensajes\Livewire\BandejaMensajes;
use Modules\Mensajes\Livewire\HiloMensajes;
use Modules\Mensajes\Models\MensajeParticipante;
use Modules\Mensajes\Services\MensajeriaService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo 10: Componentes BandejaMensajes e HiloMensajes.
 *
 * @see docs/modulo-mensajes.md §10
 */
class BandejaMensajesHiloTest extends TestCase
{
    use RefreshDatabase;

    private MensajeriaService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = new MensajeriaService;
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * T-LW-06 — La bandeja muestra solo los hilos en los que el usuario participa.
     */
    #[Test]
    public function t_lw_06_bandeja_muestra_solo_hilos_del_usuario(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();
        $usuarioC = User::factory()->create();

        $hiloAB = $this->servicio->crearHilo($usuarioA, $usuarioB, 'Hilo A-B', 'Mensaje AB');
        $hiloBC = $this->servicio->crearHilo($usuarioB, $usuarioC, 'Hilo B-C', 'Mensaje BC');

        Livewire::actingAs($usuarioA)
            ->test(BandejaMensajes::class)
            ->assertSee('Hilo A-B')
            ->assertDontSee('Hilo B-C');
    }

    /**
     * T-LW-07 — Un hilo archivado no aparece en la bandeja principal.
     * El otro participante sí sigue viéndolo.
     */
    #[Test]
    public function t_lw_07_hilo_archivado_no_aparece_en_bandeja(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();

        $hilo = $this->servicio->crearHilo($usuarioA, $usuarioB, 'Hilo a archivar', 'Mensaje');

        // Archivar el hilo para usuarioA
        MensajeParticipante::where('hilo_id', $hilo->id)
            ->where('usuario_id', $usuarioA->id)
            ->update(['archivado_en' => now()]);

        // UsuarioA no lo ve
        Livewire::actingAs($usuarioA)
            ->test(BandejaMensajes::class)
            ->assertDontSee('Hilo a archivar');

        // UsuarioB sigue viéndolo
        Livewire::actingAs($usuarioB)
            ->test(BandejaMensajes::class)
            ->assertSee('Hilo a archivar');
    }

    /**
     * T-LW-08 — El botón de registrar en historia solo es visible para el TSR responsable.
     */
    #[Test]
    public function t_lw_08_boton_registrar_historia_solo_visible_para_tsr(): void
    {
        $ciudadano = Ciudadano::factory()->create();

        // Profesional A: TSR responsable del ciudadano
        $uoA = UnidadOrganizativa::create(['nombre' => 'UO A LW08', 'tipo' => 'servicio', 'activa' => true]);
        $usuarioA = User::factory()->create();

        UsuarioUo::create([
            'usuario_id' => $usuarioA->id,
            'unidad_organizativa_id' => $uoA->id,
            'tipo_vinculo' => 'adscripcion',
            'fecha_inicio' => now()->toDateString(),
        ]);

        HistoriaSocial::create([
            'ciudadano_id' => $ciudadano->id,
            'unidad_organizativa_id' => $uoA->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        // Profesional B: no es TSR del ciudadano
        $usuarioB = User::factory()->create();

        // Hilo de A (con referencia al ciudadano)
        $otroUsuario = User::factory()->create();
        $hiloA = $this->servicio->crearHilo($usuarioA, $otroUsuario, 'Hilo de A', 'Mensaje', [$ciudadano->id]);

        // Hilo de B (con referencia al mismo ciudadano)
        $hiloB = $this->servicio->crearHilo($usuarioB, $otroUsuario, 'Hilo de B', 'Mensaje B', [$ciudadano->id]);

        // A ve el botón en su hilo
        Livewire::actingAs($usuarioA)
            ->test(HiloMensajes::class, ['hiloId' => $hiloA->id])
            ->assertSee('journal-plus');

        // B NO ve el botón en su hilo
        Livewire::actingAs($usuarioB)
            ->test(HiloMensajes::class, ['hiloId' => $hiloB->id])
            ->assertDontSee('journal-plus');
    }
}
