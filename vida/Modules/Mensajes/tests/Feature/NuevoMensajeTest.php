<?php

namespace Modules\Mensajes\Tests\Feature;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Mensajes\Livewire\NuevoMensaje;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo 11: Componente Livewire NuevoMensaje.
 *
 * @see docs/modulo-mensajes.md §11
 */
class NuevoMensajeTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * T-LW-09 — Filtrar destinatarios por rol y UO devuelve solo los que cumplen ambas condiciones.
     */
    #[Test]
    public function t_lw_09_filtrar_destinatarios_por_rol_y_uo(): void
    {
        $uo1 = UnidadOrganizativa::create(['nombre' => 'UO1 LW09', 'tipo' => 'servicio', 'activa' => true]);
        $uo2 = UnidadOrganizativa::create(['nombre' => 'UO2 LW09', 'tipo' => 'servicio', 'activa' => true]);

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'trabajador_social', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'educador_social',   'guard_name' => 'web']);

        $ts1      = User::factory()->create(['name' => 'Kandidat TS Uno']);
        $ts2      = User::factory()->create(['name' => 'Kandidat TS Dos']);
        $educador = User::factory()->create(['name' => 'Kandidat EDU']);
        $tsOtraUo = User::factory()->create(['name' => 'Kandidat TSO Otra']);
        $remitente = User::factory()->create(['name' => 'Remitente Fijo']);

        foreach ([$ts1, $ts2, $educador] as $u) {
            UsuarioUo::create([
                'usuario_id'             => $u->id,
                'unidad_organizativa_id' => $uo1->id,
                'tipo_vinculo'           => 'adscripcion',
                'fecha_inicio'           => now()->toDateString(),
            ]);
        }

        UsuarioUo::create([
            'usuario_id'             => $tsOtraUo->id,
            'unidad_organizativa_id' => $uo2->id,
            'tipo_vinculo'           => 'adscripcion',
            'fecha_inicio'           => now()->toDateString(),
        ]);

        $ts1->assignRole('trabajador_social');
        $ts2->assignRole('trabajador_social');
        $educador->assignRole('educador_social');
        $tsOtraUo->assignRole('trabajador_social');

        Livewire::actingAs($remitente)
            ->test(NuevoMensaje::class)
            ->set('filtroPorRol', 'trabajador_social')
            ->set('filtroUoId', $uo1->id)
            ->set('busquedaDestinatario', 'Kandidat')
            ->assertSee('Kandidat TS Uno')
            ->assertSee('Kandidat TS Dos')
            ->assertDontSee('Kandidat EDU')
            ->assertDontSee('Kandidat TSO Otra');
    }

    /**
     * T-LW-10 — El formulario no se envía sin asunto.
     */
    #[Test]
    public function t_lw_10_formulario_no_se_envia_sin_asunto(): void
    {
        $remitente    = User::factory()->create();
        $destinatario = User::factory()->create();

        Livewire::actingAs($remitente)
            ->test(NuevoMensaje::class)
            ->set('destinatarioId', $destinatario->id)
            ->set('asunto', '')
            ->set('cuerpo', 'Cuerpo del mensaje')
            ->call('enviar')
            ->assertHasErrors(['asunto' => 'required']);

        $this->assertDatabaseCount('mensajes_hilos', 0);
    }

    /**
     * T-LW-11 — El formulario no se envía sin cuerpo de mensaje.
     */
    #[Test]
    public function t_lw_11_formulario_no_se_envia_sin_cuerpo(): void
    {
        $remitente    = User::factory()->create();
        $destinatario = User::factory()->create();

        Livewire::actingAs($remitente)
            ->test(NuevoMensaje::class)
            ->set('destinatarioId', $destinatario->id)
            ->set('asunto', 'Asunto de prueba')
            ->set('cuerpo', '')
            ->call('enviar')
            ->assertHasErrors(['cuerpo' => 'required']);

        $this->assertDatabaseCount('mensajes_hilos', 0);
    }

    /**
     * T-LW-12 — El formulario no se envía sin destinatario.
     */
    #[Test]
    public function t_lw_12_formulario_no_se_envia_sin_destinatario(): void
    {
        $remitente = User::factory()->create();

        Livewire::actingAs($remitente)
            ->test(NuevoMensaje::class)
            ->set('destinatarioId', null)
            ->set('asunto', 'Asunto')
            ->set('cuerpo', 'Cuerpo')
            ->call('enviar')
            ->assertHasErrors(['destinatarioId' => 'required']);

        $this->assertDatabaseCount('mensajes_hilos', 0);
    }

    /**
     * T-LW-13 — Un usuario no puede enviarse un mensaje a sí mismo.
     */
    #[Test]
    public function t_lw_13_usuario_no_puede_enviarse_mensaje_a_si_mismo(): void
    {
        $usuario = User::factory()->create();

        Livewire::actingAs($usuario)
            ->test(NuevoMensaje::class)
            ->set('destinatarioId', $usuario->id)
            ->set('asunto', 'Asunto')
            ->set('cuerpo', 'Cuerpo')
            ->call('enviar')
            ->assertHasErrors(['destinatarioId']);

        $this->assertDatabaseCount('mensajes_hilos', 0);
    }
}
