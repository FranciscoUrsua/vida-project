<?php

namespace Modules\Mensajes\Tests\Feature\Livewire;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Intervencion\Http\Livewire\Sidebar as SidebarIntervencion;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\EstadoAlerta;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Http\Livewire\BandejaAlertasYMensajes;
use Modules\Mensajes\Livewire\BandejaAlertas;
use Modules\Mensajes\Livewire\HiloMensajes;
use Modules\Mensajes\Models\Alerta;
use Modules\Mensajes\Services\AlertaService;
use Modules\Mensajes\Services\MensajeriaService;
use Modules\Supervision\Http\Livewire\Sidebar as SidebarSupervision;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bandeja unificada de alertas, avisos y mensajes (paso 2 del plan de
 * `instrucciones-cli-mensajes.md`): tres entradas de menú que abren la misma
 * pantalla con la pestaña correspondiente. TF-MSG-BAN-01 a 13.
 */
class BandejaAlertasYMensajesTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uo;

    private User $profesional;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->uo = UnidadOrganizativa::create(['nombre' => 'CSS Bandeja', 'tipo' => 'centro', 'activa' => true]);
        $this->profesional = $this->crearUsuario('prof@vida360.test', ['intervencion']);
        $this->supervisor = $this->crearUsuario('sup@vida360.test', ['supervision']);
    }

    /**
     * Crea un usuario con roles y adscripción vigente a la UO de prueba.
     *
     * @param string[] $roles
     */
    private function crearUsuario(string $email, array $roles): User
    {
        $usuario = User::create([
            'name' => $email,
            'email' => $email,
            'password' => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso' => false,
        ]);
        $usuario->assignRole($roles);

        UsuarioUo::create([
            'usuario_id' => $usuario->id,
            'unidad_organizativa_id' => $this->uo->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today()->subYear()->toDateString(),
        ]);

        return $usuario;
    }

    /**
     * Crea por el servicio una alerta o aviso directo.
     */
    private function crear(User $para, TipoAlerta $tipo, string $titulo, string $origenType = User::class): Alerta
    {
        return app(AlertaService::class)->crear([
            'tipo' => $tipo,
            'origen_type' => $origenType,
            'origen_id' => $this->supervisor->id,
            'titulo' => $titulo,
            'cuerpo' => "Cuerpo de {$titulo}",
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $para->id,
        ]);
    }

    /** TF-MSG-BAN-01 — Cada entrada de menú abre la bandeja con su pestaña. */
    #[Test]
    public function cada_ruta_abre_su_pestana(): void
    {
        $this->actingAs($this->profesional);

        $this->get(route('intervencion.mensajes.index'))->assertOk();

        foreach (['alertas', 'avisos', 'mensajes'] as $pestana) {
            $this->get(route('intervencion.mensajes.index', $pestana))->assertOk();

            Livewire::test(BandejaAlertasYMensajes::class, ['pestana' => $pestana])
                ->assertSet('pestana', $pestana);
        }
    }

    /** TF-MSG-BAN-02 — Una pestaña inexistente da 404. */
    #[Test]
    public function pestana_inexistente_da_404(): void
    {
        $this->actingAs($this->profesional)
            ->get('/intervencion/mensajes/otra')
            ->assertNotFound();
    }

    /** TF-MSG-BAN-03 — Un supervisor sin rol de intervención llega a la bandeja desde su interfaz. */
    #[Test]
    public function supervisor_accede_desde_su_interfaz(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('supervision.bandeja', 'alertas'))
            ->assertOk();
    }

    /** TF-MSG-BAN-04 — La ruta de supervisión exige el rol de supervisión. */
    #[Test]
    public function ruta_de_supervision_exige_rol(): void
    {
        $this->actingAs($this->profesional)
            ->get(route('supervision.bandeja', 'alertas'))
            ->assertForbidden();
    }

    /** TF-MSG-BAN-05 — La pestaña Alertas solo lista alertas y la de Avisos solo avisos. */
    #[Test]
    public function cada_pestana_lista_su_tipo(): void
    {
        $this->crear($this->profesional, TipoAlerta::Alerta, 'Una alerta');
        $this->crear($this->profesional, TipoAlerta::Aviso, 'Un aviso');

        Livewire::actingAs($this->profesional)
            ->test(BandejaAlertas::class, ['tipo' => 'alerta'])
            ->assertSee('Una alerta')
            ->assertDontSee('Un aviso');

        Livewire::actingAs($this->profesional)
            ->test(BandejaAlertas::class, ['tipo' => 'aviso'])
            ->assertSee('Un aviso')
            ->assertDontSee('Una alerta');
    }

    /** TF-MSG-BAN-06 — Un aviso se descarta sin confirmación y deja de estar pendiente. */
    #[Test]
    public function descartar_aviso_sin_confirmacion(): void
    {
        $aviso = $this->crear($this->profesional, TipoAlerta::Aviso, 'Aviso a descartar');

        Livewire::actingAs($this->profesional)
            ->test(BandejaAlertas::class, ['tipo' => 'aviso'])
            ->call('descartar', $aviso->id)
            ->assertDontSee('Aviso a descartar');

        $this->assertSame(EstadoAlerta::Reconocida, $aviso->fresh()->estado);
    }

    /** TF-MSG-BAN-07 — Descartar no sirve para alertas: exigen reconocimiento con confirmación. */
    #[Test]
    public function descartar_no_sirve_para_alertas(): void
    {
        $alerta = $this->crear($this->profesional, TipoAlerta::Alerta, 'Alerta seria');

        Livewire::actingAs($this->profesional)
            ->test(BandejaAlertas::class, ['tipo' => 'alerta'])
            ->call('descartar', $alerta->id)
            ->assertForbidden();

        $this->assertSame(EstadoAlerta::Pendiente, $alerta->fresh()->estado);
    }

    /** TF-MSG-BAN-08 — Los avisos del supervisor llevan su etiqueta. */
    #[Test]
    public function aviso_del_supervisor_lleva_etiqueta(): void
    {
        $this->crear($this->profesional, TipoAlerta::Aviso, 'Reunión el jueves', 'supervisor_manual');
        $this->crear($this->profesional, TipoAlerta::Aviso, 'Aviso del sistema');

        $html = Livewire::actingAs($this->profesional)
            ->test(BandejaAlertas::class, ['tipo' => 'aviso'])
            ->html();

        $this->assertSame(1, substr_count($html, 'Aviso del supervisor'));
    }

    /** TF-MSG-BAN-09 — El menú de intervención tiene las tres entradas, cada una con su contador. */
    #[Test]
    public function menu_intervencion_tiene_tres_entradas_con_contador(): void
    {
        $this->crear($this->profesional, TipoAlerta::Alerta, 'A1');
        $this->crear($this->profesional, TipoAlerta::Aviso, 'V1');
        $this->crear($this->profesional, TipoAlerta::Aviso, 'V2');

        $componente = Livewire::actingAs($this->profesional)->test(SidebarIntervencion::class);

        $componente->assertSee(route('intervencion.mensajes.index', 'alertas'))
            ->assertSee(route('intervencion.mensajes.index', 'avisos'))
            ->assertSee(route('intervencion.mensajes.index', 'mensajes'));

        $datos = $componente->instance()->datos;
        $this->assertSame(1, $datos['alertas']);
        $this->assertSame(2, $datos['avisos']);
        $this->assertSame(0, $datos['mensajes']);
    }

    /** TF-MSG-BAN-10 — El menú de supervisión también tiene las tres entradas. */
    #[Test]
    public function menu_supervision_tiene_tres_entradas(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(SidebarSupervision::class)
            ->assertSee(route('supervision.bandeja', 'alertas'))
            ->assertSee(route('supervision.bandeja', 'avisos'))
            ->assertSee(route('supervision.bandeja', 'mensajes'));
    }

    /** TF-MSG-BAN-11 — Quien no participa en un hilo no puede abrirlo. */
    #[Test]
    public function no_participante_no_abre_hilo(): void
    {
        $otro = $this->crearUsuario('otro@vida360.test', ['intervencion']);
        $hilo = app(MensajeriaService::class)->crearHilo($this->supervisor, $otro, 'Privado', 'Contenido privado');

        Livewire::actingAs($this->profesional)
            ->test(HiloMensajes::class, ['hiloId' => $hilo->id])
            ->assertForbidden();
    }

    /** TF-MSG-BAN-12 — El hilo abierto no se puede cambiar por otro desde el navegador. */
    #[Test]
    public function hilo_id_no_se_puede_manipular(): void
    {
        $otro = $this->crearUsuario('otro2@vida360.test', ['intervencion']);
        $propio = app(MensajeriaService::class)->crearHilo($this->supervisor, $this->profesional, 'Propio', 'Hola');
        $ajeno = app(MensajeriaService::class)->crearHilo($this->supervisor, $otro, 'Ajeno', 'Secreto');

        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->profesional)
            ->test(HiloMensajes::class, ['hiloId' => $propio->id])
            ->set('hiloId', $ajeno->id);
    }

    /** TF-MSG-BAN-13 — Responder en un hilo propio añade el mensaje. */
    #[Test]
    public function responder_en_hilo_propio(): void
    {
        $hilo = app(MensajeriaService::class)->crearHilo($this->supervisor, $this->profesional, 'Consulta', 'Pregunta');

        Livewire::actingAs($this->profesional)
            ->test(HiloMensajes::class, ['hiloId' => $hilo->id])
            ->set('respuesta', 'Respuesta del profesional')
            ->call('enviarRespuesta')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('mensajes', [
            'hilo_id' => $hilo->id,
            'remitente_id' => $this->profesional->id,
            'cuerpo' => 'Respuesta del profesional',
        ]);
    }
}
