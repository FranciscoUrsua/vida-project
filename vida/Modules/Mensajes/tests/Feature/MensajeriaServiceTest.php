<?php

namespace Modules\Mensajes\Tests\Feature;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Mensajes\Enums\RolParticipante;
use Modules\Mensajes\Exceptions\UnauthorizedException;
use Modules\Mensajes\Models\Mensaje;
use Modules\Mensajes\Models\MensajeHilo;
use Modules\Mensajes\Services\MensajeriaService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del MensajeriaService.
 */
class MensajeriaServiceTest extends TestCase
{
    use RefreshDatabase;

    private MensajeriaService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = new MensajeriaService();
    }

    // -------------------------------------------------------------------------
    // Crear hilo
    // -------------------------------------------------------------------------

    #[Test]
    public function crear_hilo_genera_dos_participantes(): void
    {
        $remitente   = User::factory()->create();
        $destinatario = User::factory()->create();

        $hilo = $this->servicio->crearHilo(
            remitente:    $remitente,
            destinatario: $destinatario,
            asunto:       'Consulta sobre expediente',
            cuerpo:       'Hola, necesito información.',
        );

        $this->assertDatabaseCount('mensajes_participantes', 2);

        $this->assertDatabaseHas('mensajes_participantes', [
            'hilo_id'    => $hilo->id,
            'usuario_id' => $remitente->id,
            'rol'        => RolParticipante::RemitenteInicial->value,
        ]);

        $this->assertDatabaseHas('mensajes_participantes', [
            'hilo_id'    => $hilo->id,
            'usuario_id' => $destinatario->id,
            'rol'        => RolParticipante::Participante->value,
        ]);
    }

    #[Test]
    public function crear_hilo_crea_el_primer_mensaje(): void
    {
        $remitente   = User::factory()->create();
        $destinatario = User::factory()->create();

        $hilo = $this->servicio->crearHilo(
            remitente:    $remitente,
            destinatario: $destinatario,
            asunto:       'Test',
            cuerpo:       'Primer mensaje del hilo',
        );

        $this->assertDatabaseHas('mensajes', [
            'hilo_id'      => $hilo->id,
            'remitente_id' => $remitente->id,
            'cuerpo'       => 'Primer mensaje del hilo',
        ]);
    }

    #[Test]
    public function crear_hilo_con_ciudadano_referenciado_crea_la_referencia(): void
    {
        $remitente   = User::factory()->create();
        $destinatario = User::factory()->create();
        $ciudadano    = Ciudadano::factory()->create();

        $this->servicio->crearHilo(
            remitente:    $remitente,
            destinatario: $destinatario,
            asunto:       'Sobre ciudadano',
            cuerpo:       'Referenciando al ciudadano',
            ciudadanoIds: [$ciudadano->id],
        );

        $this->assertDatabaseHas('mensajes_referencias_ciudadano', [
            'ciudadano_id' => $ciudadano->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Registrar en Historia Social
    // -------------------------------------------------------------------------

    #[Test]
    public function registrar_en_historia_lanza_excepcion_si_usuario_no_es_tsr(): void
    {
        $remitente   = User::factory()->create();
        $destinatario = User::factory()->create();
        $noTsr        = User::factory()->create();
        $ciudadano    = Ciudadano::factory()->create();

        $hilo = $this->servicio->crearHilo($remitente, $destinatario, 'Test', 'Cuerpo');
        $mensaje = $hilo->mensajes()->first();

        $this->expectException(UnauthorizedException::class);

        $this->servicio->registrarEnHistoria(
            mensaje:       $mensaje,
            ciudadano:     $ciudadano,
            tsr:           $noTsr,
            cuerpoEditado: 'Versión editada',
        );
    }

    #[Test]
    public function registrar_en_historia_crea_registro_con_cuerpo_editado(): void
    {
        // Crear UO, ciudadano e historia social asignada a la UO del TSR
        $uo        = UnidadOrganizativa::create(['nombre' => 'UO TSR', 'tipo' => 'servicio', 'activa' => true]);
        $tsr       = User::factory()->create();
        $ciudadano  = Ciudadano::factory()->create();

        UsuarioUo::create([
            'usuario_id'             => $tsr->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo'           => 'adscripcion',
            'fecha_inicio'           => now()->toDateString(),
        ]);

        HistoriaSocial::create([
            'ciudadano_id'             => $ciudadano->id,
            'unidad_organizativa_id'   => $uo->id,
            'ciudadano_protegido'      => false,
            'estado'                   => 'abierta',
        ]);

        $remitente   = User::factory()->create();
        $destinatario = User::factory()->create();
        $hilo         = $this->servicio->crearHilo($remitente, $destinatario, 'Asunto', 'Mensaje original');
        $mensaje      = $hilo->mensajes()->first();

        $registro = $this->servicio->registrarEnHistoria(
            mensaje:       $mensaje,
            ciudadano:     $ciudadano,
            tsr:           $tsr,
            cuerpoEditado: 'Versión editada por el TSR',
        );

        $this->assertEquals('Versión editada por el TSR', $registro->cuerpo_registrado);
        $this->assertNotEquals($mensaje->cuerpo, $registro->cuerpo_registrado);

        $this->assertDatabaseHas('mensajes_registro_historia', [
            'mensaje_id'        => $mensaje->id,
            'ciudadano_id'      => $ciudadano->id,
            'registrado_por_id' => $tsr->id,
            'cuerpo_registrado' => 'Versión editada por el TSR',
        ]);
    }

    // -------------------------------------------------------------------------
    // Marcar como leído
    // -------------------------------------------------------------------------

    #[Test]
    public function marcar_como_leido_actualiza_fecha_ultima_lectura(): void
    {
        $remitente   = User::factory()->create();
        $destinatario = User::factory()->create();

        $hilo = $this->servicio->crearHilo($remitente, $destinatario, 'Test', 'Hola');

        $this->servicio->marcarComoLeido($hilo, $destinatario);

        $participante = $hilo->participantes()->where('usuario_id', $destinatario->id)->first();

        $this->assertNotNull($participante->fecha_ultima_lectura);
    }
}
