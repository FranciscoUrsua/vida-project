<?php

namespace Modules\Mensajes\Tests\Feature\Livewire;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\EstadoAlerta;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Http\Livewire\BuzonPage;
use Modules\Mensajes\Models\Alerta;
use Modules\Mensajes\Models\MensajeHilo;
use Modules\Mensajes\Models\MensajeParticipante;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales del componente Livewire BuzonPage.
 *
 * TF-LW-BUZ-01 a TF-LW-BUZ-06
 *
 * @see docs/instrucciones-cli/ui-intervencion-entrega2.md §6
 */
class BuzonPageTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $uo = UnidadOrganizativa::create([
            'nombre'    => 'CSS Test Buzon',
            'tipo'      => 'centro',
            'parent_id' => null,
            'activa'    => true,
        ]);

        $this->usuario = User::create([
            'name'              => 'Profesional Test',
            'email'             => 'buzon@vida360.test',
            'password'          => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso'     => false,
        ]);

        $this->usuario->assignRole('intervencion');

        UsuarioUo::create([
            'usuario_id'             => $this->usuario->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo'           => 'interno',
            'fecha_inicio'           => today()->toDateString(),
        ]);
    }

    /**
     * Crea una alerta directa al usuario.
     *
     * @param User $usuario
     * @param TipoAlerta $tipo
     * @return Alerta
     */
    private function crearAlerta(User $usuario, TipoAlerta $tipo = TipoAlerta::Alerta): Alerta
    {
        return Alerta::create([
            'tipo'                   => $tipo,
            'origen_type'            => User::class,
            'origen_id'              => $usuario->id,
            'titulo'                 => 'Alerta de prueba',
            'cuerpo'                 => 'Cuerpo de la alerta de prueba.',
            'destinatario_type'      => DestinatarioType::Usuario,
            'destinatario_usuario_id'=> $usuario->id,
            'estado'                 => EstadoAlerta::Pendiente,
        ]);
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * TF-LW-BUZ-01 — La pestaña Alertas muestra solo las alertas del usuario autenticado.
     */
    #[Test]
    public function pestana_alertas_muestra_solo_las_del_usuario(): void
    {
        $otro = User::create([
            'name'              => 'Otro user',
            'email'             => 'otro-buzon@vida360.test',
            'password'          => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso'     => false,
        ]);

        $this->crearAlerta($this->usuario);
        $this->crearAlerta($this->usuario);
        $this->crearAlerta($otro); // No debe aparecer

        $componente = Livewire::actingAs($this->usuario)
            ->test(BuzonPage::class)
            ->set('pestana', 'alertas');

        $this->assertCount(2, $componente->get('alertas'));
    }

    /**
     * TF-LW-BUZ-02 — Reconocer una alerta rellena estado = 'reconocida' y la saca de la lista.
     */
    #[Test]
    public function reconocer_alerta_la_retira_de_la_lista(): void
    {
        $alerta = $this->crearAlerta($this->usuario);

        Livewire::actingAs($this->usuario)
            ->test(BuzonPage::class)
            ->call('reconocerAlerta', $alerta->id);

        $this->assertDatabaseHas('alertas', [
            'id'     => $alerta->id,
            'estado' => EstadoAlerta::Reconocida->value,
        ]);
    }

    /**
     * TF-LW-BUZ-03 — No se puede reconocer la alerta de otro usuario.
     */
    #[Test]
    public function no_se_puede_reconocer_alerta_de_otro_usuario(): void
    {
        $otro = User::create([
            'name'              => 'Otro user 03',
            'email'             => 'otro03@vida360.test',
            'password'          => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso'     => false,
        ]);

        $alertaAjena = $this->crearAlerta($otro);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::actingAs($this->usuario)
            ->test(BuzonPage::class)
            ->call('reconocerAlerta', $alertaAjena->id);
    }

    /**
     * TF-LW-BUZ-04 — La pestaña Mensajes muestra solo los hilos en los que participa el usuario.
     */
    #[Test]
    public function pestana_mensajes_muestra_solo_hilos_propios(): void
    {
        $remitente = User::create([
            'name'              => 'Remitente',
            'email'             => 'remitente@vida360.test',
            'password'          => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso'     => false,
        ]);

        // Hilo donde participa el usuario
        $hilo1 = MensajeHilo::create(['asunto' => 'Hilo del usuario', 'creado_por_id' => $this->usuario->id]);
        MensajeParticipante::create([
            'hilo_id'    => $hilo1->id,
            'usuario_id' => $this->usuario->id,
            'rol'        => 'participante',
        ]);

        // Hilo donde NO participa el usuario
        $hilo2 = MensajeHilo::create(['asunto' => 'Hilo ajeno', 'creado_por_id' => $remitente->id]);
        MensajeParticipante::create([
            'hilo_id'    => $hilo2->id,
            'usuario_id' => $remitente->id,
            'rol'        => 'remitente_inicial',
        ]);

        $componente = Livewire::actingAs($this->usuario)
            ->test(BuzonPage::class)
            ->set('pestana', 'mensajes');

        $this->assertCount(1, $componente->get('hilos'));
    }

    /**
     * TF-LW-BUZ-05 — Enviar respuesta añade el mensaje al hilo con el user_id correcto.
     */
    #[Test]
    public function enviar_respuesta_crea_mensaje_del_usuario(): void
    {
        $hilo = MensajeHilo::create(['asunto' => 'Hilo respuesta', 'creado_por_id' => $this->usuario->id]);
        MensajeParticipante::create([
            'hilo_id'    => $hilo->id,
            'usuario_id' => $this->usuario->id,
            'rol'        => 'participante',
        ]);

        Livewire::actingAs($this->usuario)
            ->test(BuzonPage::class)
            ->set('pestana', 'mensajes')
            ->set('respuesta', 'Este es mi mensaje de respuesta de prueba.')
            ->call('enviarRespuesta', $hilo->id);

        $this->assertDatabaseHas('mensajes', [
            'hilo_id'      => $hilo->id,
            'remitente_id' => $this->usuario->id,
        ]);
    }

    /**
     * TF-LW-BUZ-06 — Los avisos aparecen en la pestaña Avisos, no en Alertas.
     */
    #[Test]
    public function avisos_aparecen_en_pestana_avisos_no_en_alertas(): void
    {
        $aviso  = $this->crearAlerta($this->usuario, TipoAlerta::Aviso);
        $alerta = $this->crearAlerta($this->usuario, TipoAlerta::Alerta);

        $componente = Livewire::actingAs($this->usuario)->test(BuzonPage::class);

        $this->assertCount(1, $componente->get('alertas'));
        $this->assertCount(1, $componente->get('avisos'));
        $this->assertEquals($alerta->id, $componente->get('alertas')->first()->id);
        $this->assertEquals($aviso->id, $componente->get('avisos')->first()->id);
    }
}
