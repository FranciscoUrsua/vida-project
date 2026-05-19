<?php

namespace Modules\Mensajes\Tests\Feature;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Mensajes\Enums\RolParticipante;
use Modules\Mensajes\Enums\VisibilidadMensaje;
use Modules\Mensajes\Exceptions\UnauthorizedException;
use Modules\Mensajes\Models\Mensaje;
use Modules\Mensajes\Models\MensajeHilo;
use Modules\Mensajes\Models\MensajeParticipante;
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

    // -------------------------------------------------------------------------
    // T-MSG-04 a T-MSG-10 — escenarios exactos del spec
    // -------------------------------------------------------------------------

    /**
     * T-MSG-04 — Responder añade un mensaje al hilo sin crear uno nuevo.
     */
    #[Test]
    public function t_msg_04_responder_anade_mensaje_al_hilo_sin_crear_uno_nuevo(): void
    {
        $remitente    = User::factory()->create();
        $destinatario = User::factory()->create();

        $hilo = $this->servicio->crearHilo($remitente, $destinatario, 'Test', 'Primer mensaje');

        $this->assertDatabaseCount('mensajes_hilos', 1);
        $this->assertDatabaseCount('mensajes', 1);

        $this->servicio->responder($hilo, $destinatario, 'Respuesta del destinatario');

        $this->assertDatabaseCount('mensajes_hilos', 1);
        $this->assertDatabaseCount('mensajes', 2);

        $segundoMensaje = $hilo->mensajes()->orderBy('id', 'desc')->first();
        $this->assertEquals($destinatario->id, $segundoMensaje->remitente_id);
    }

    /**
     * T-MSG-06 — El contador de mensajes no leídos es correcto.
     * Tres mensajes, destinatario no ha leído → 3. Tras marcar leído → 0.
     */
    #[Test]
    public function t_msg_06_contador_mensajes_no_leidos_es_correcto(): void
    {
        $remitente    = User::factory()->create();
        $destinatario = User::factory()->create();

        $hilo = $this->servicio->crearHilo($remitente, $destinatario, 'Test', 'Mensaje 1');
        $this->servicio->responder($hilo, $remitente, 'Mensaje 2');
        $this->servicio->responder($hilo, $remitente, 'Mensaje 3');

        $participante = MensajeParticipante::where('hilo_id', $hilo->id)
            ->where('usuario_id', $destinatario->id)
            ->first();

        $this->assertNull($participante->fecha_ultima_lectura);
        $this->assertEquals(3, $participante->mensajesNoLeidos());

        $this->servicio->marcarComoLeido($hilo, $destinatario);

        $this->assertEquals(0, $participante->fresh()->mensajesNoLeidos());
    }

    /**
     * T-MSG-07 — El TSR responsable puede registrar un mensaje en la Historia Social.
     */
    #[Test]
    public function t_msg_07_tsr_puede_registrar_mensaje_en_historia_social(): void
    {
        $uo        = UnidadOrganizativa::create(['nombre' => 'UO TSR 07', 'tipo' => 'servicio', 'activa' => true]);
        $tsr       = User::factory()->create();
        $ciudadano = Ciudadano::factory()->create();

        UsuarioUo::create([
            'usuario_id'             => $tsr->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo'           => 'adscripcion',
            'fecha_inicio'           => now()->toDateString(),
        ]);

        HistoriaSocial::create([
            'ciudadano_id'           => $ciudadano->id,
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido'    => false,
            'estado'                 => 'abierta',
        ]);

        $hilo    = $this->servicio->crearHilo(User::factory()->create(), User::factory()->create(), 'Test', 'Original');
        $mensaje = $hilo->mensajes()->first();

        $registro = $this->servicio->registrarEnHistoria(
            mensaje:       $mensaje,
            ciudadano:     $ciudadano,
            tsr:           $tsr,
            cuerpoEditado: 'Texto editado por TSR',
            visibilidad:   'profesionales',
        );

        $this->assertEquals($ciudadano->id, $registro->ciudadano_id);
        $this->assertEquals($tsr->id, $registro->registrado_por_id);
        $this->assertEquals('Texto editado por TSR', $registro->cuerpo_registrado);
        $this->assertEquals(VisibilidadMensaje::Profesionales, $registro->visibilidad);
    }

    /**
     * T-MSG-09 — La visibilidad por defecto del registro es 'profesionales'.
     */
    #[Test]
    public function t_msg_09_visibilidad_por_defecto_es_profesionales(): void
    {
        $uo        = UnidadOrganizativa::create(['nombre' => 'UO TSR 09', 'tipo' => 'servicio', 'activa' => true]);
        $tsr       = User::factory()->create();
        $ciudadano = Ciudadano::factory()->create();

        UsuarioUo::create([
            'usuario_id'             => $tsr->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo'           => 'adscripcion',
            'fecha_inicio'           => now()->toDateString(),
        ]);

        HistoriaSocial::create([
            'ciudadano_id'           => $ciudadano->id,
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido'    => false,
            'estado'                 => 'abierta',
        ]);

        $hilo    = $this->servicio->crearHilo(User::factory()->create(), User::factory()->create(), 'Test', 'Mensaje');
        $mensaje = $hilo->mensajes()->first();

        // Sin especificar visibilidad → debe usar el valor por defecto
        $registro = $this->servicio->registrarEnHistoria(
            mensaje:       $mensaje,
            ciudadano:     $ciudadano,
            tsr:           $tsr,
            cuerpoEditado: 'Contenido',
        );

        $this->assertEquals(VisibilidadMensaje::Profesionales, $registro->visibilidad);
    }

    /**
     * T-MSG-10 — No se permite registrar con visibilidad 'ciudadano'.
     * Los registros de comunicación interna nunca pueden ser visibles para el ciudadano.
     */
    #[Test]
    public function t_msg_10_no_se_permite_registrar_con_visibilidad_ciudadano(): void
    {
        $uo        = UnidadOrganizativa::create(['nombre' => 'UO TSR 10', 'tipo' => 'servicio', 'activa' => true]);
        $tsr       = User::factory()->create();
        $ciudadano = Ciudadano::factory()->create();

        UsuarioUo::create([
            'usuario_id'             => $tsr->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo'           => 'adscripcion',
            'fecha_inicio'           => now()->toDateString(),
        ]);

        HistoriaSocial::create([
            'ciudadano_id'           => $ciudadano->id,
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido'    => false,
            'estado'                 => 'abierta',
        ]);

        $hilo    = $this->servicio->crearHilo(User::factory()->create(), User::factory()->create(), 'Test', 'Mensaje');
        $mensaje = $hilo->mensajes()->first();

        $this->expectException(\InvalidArgumentException::class);

        $this->servicio->registrarEnHistoria(
            mensaje:       $mensaje,
            ciudadano:     $ciudadano,
            tsr:           $tsr,
            cuerpoEditado: 'Contenido',
            visibilidad:   'ciudadano',
        );

        $this->assertDatabaseCount('mensajes_registro_historia', 0);
    }
}
