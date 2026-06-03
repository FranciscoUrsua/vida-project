<?php

namespace Modules\Mensajes\Tests\Feature;

use App\Models\CatalogoSistema;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\EstadoAlerta;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Enums\TipoReconocimiento;
use Modules\Mensajes\Models\Alerta;
use Modules\Mensajes\Services\AlertaService;
use Modules\Mensajes\Services\HorarioLaboralService;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests del AlertaService.
 */
class AlertaServiceTest extends TestCase
{
    use RefreshDatabase;

    private AlertaService $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        // Horario laboral para tests
        CatalogoSistema::create([
            'grupo' => 'sistema.configuracion',
            'clave' => 'horario_laboral_defecto',
            'etiqueta' => 'Horario laboral por defecto',
            'valor' => json_encode(['inicio' => '08:00', 'fin' => '17:00', 'dias_semana' => [1, 2, 3, 4, 5]]),
            'orden' => 1,
            'activo' => true,
        ]);

        $this->servicio = new AlertaService(new HorarioLaboralService);

        // Crear roles necesarios para los tests de escalada
        Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
    }

    private function datosAvisoBásico(array $overrides = []): array
    {
        $usuario = User::factory()->create();

        return array_merge([
            'tipo' => TipoAlerta::Aviso,
            'origen_type' => 'App\\Models\\User',
            'origen_id' => $usuario->id,
            'titulo' => 'Aviso de prueba',
            'cuerpo' => 'Cuerpo del aviso',
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $usuario->id,
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Crear alertas
    // -------------------------------------------------------------------------

    #[Test]
    public function crear_aviso_no_genera_expira_en(): void
    {
        $alerta = $this->servicio->crear($this->datosAvisoBásico());

        $this->assertNull($alerta->expira_en);
        $this->assertEquals(EstadoAlerta::Pendiente, $alerta->estado);
        $this->assertDatabaseHas('alertas', ['id' => $alerta->id, 'tipo' => 'aviso']);
    }

    #[Test]
    public function crear_alerta_genera_expira_en(): void
    {
        $usuario = User::factory()->create();

        $alerta = $this->servicio->crear([
            'tipo' => TipoAlerta::Alerta,
            'origen_type' => 'App\\Models\\User',
            'origen_id' => $usuario->id,
            'titulo' => 'Alerta urgente',
            'cuerpo' => 'Requiere reconocimiento',
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $usuario->id,
        ]);

        $this->assertNotNull($alerta->expira_en);
        $this->assertTrue($alerta->expira_en->isAfter(now()));
    }

    // -------------------------------------------------------------------------
    // Reconocer alertas
    // -------------------------------------------------------------------------

    #[Test]
    public function reconocer_alerta_cambia_estado_a_reconocida(): void
    {
        $usuario = User::factory()->create();
        $alerta = $this->servicio->crear($this->datosAvisoBásico([
            'tipo' => TipoAlerta::Alerta,
            'destinatario_usuario_id' => $usuario->id,
        ]));

        $this->servicio->reconocer($alerta, $usuario, '127.0.0.1');

        $this->assertEquals(EstadoAlerta::Reconocida, $alerta->fresh()->estado);
        $this->assertDatabaseHas('alerta_reconocimientos', [
            'alerta_id' => $alerta->id,
            'usuario_id' => $usuario->id,
            'tipo' => TipoReconocimiento::Reconocida->value,
        ]);
    }

    #[Test]
    public function descartar_aviso_registra_tipo_descartada(): void
    {
        $usuario = User::factory()->create();
        $aviso = $this->servicio->crear($this->datosAvisoBásico([
            'destinatario_usuario_id' => $usuario->id,
        ]));

        $this->servicio->reconocer($aviso, $usuario, '127.0.0.1');

        $this->assertDatabaseHas('alerta_reconocimientos', [
            'alerta_id' => $aviso->id,
            'tipo' => TipoReconocimiento::Descartada->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // Escalada
    // -------------------------------------------------------------------------

    #[Test]
    public function escalar_alerta_con_supervisor_cambia_estado_a_escalada(): void
    {
        // Crear UO, usuario destinatario y supervisor con rol 'supervisor'
        $uo = UnidadOrganizativa::create(['nombre' => 'UO Test', 'tipo' => 'servicio', 'activa' => true]);
        $destinatario = User::factory()->create();
        $supervisor = User::factory()->create();

        UsuarioUo::create([
            'usuario_id' => $destinatario->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo' => 'adscripcion',
            'fecha_inicio' => now()->toDateString(),
        ]);

        UsuarioUo::create([
            'usuario_id' => $supervisor->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo' => 'adscripcion',
            'fecha_inicio' => now()->toDateString(),
        ]);

        // Asignar rol supervisor via Spatie
        $supervisor->assignRole('supervisor');

        $alerta = Alerta::create([
            'tipo' => TipoAlerta::Alerta,
            'origen_type' => 'App\\Models\\User',
            'origen_id' => $destinatario->id,
            'titulo' => 'Alerta vencida',
            'cuerpo' => 'Vencida sin reconocer',
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $destinatario->id,
            'estado' => EstadoAlerta::Pendiente,
        ]);

        $this->servicio->escalar($alerta);

        $alertaActualizada = $alerta->fresh();
        $this->assertEquals(EstadoAlerta::Escalada, $alertaActualizada->estado);
        $this->assertEquals($supervisor->id, $alertaActualizada->escalada_a_usuario_id);
        $this->assertNotNull($alertaActualizada->escalada_en);
    }

    #[Test]
    public function escalar_sin_supervisor_disponible_cambia_estado_a_vencida(): void
    {
        $uo = UnidadOrganizativa::create(['nombre' => 'UO Sin Sup', 'tipo' => 'servicio', 'activa' => true]);
        $destinatario = User::factory()->create();

        UsuarioUo::create([
            'usuario_id' => $destinatario->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo' => 'adscripcion',
            'fecha_inicio' => now()->toDateString(),
        ]);

        $alerta = Alerta::create([
            'tipo' => TipoAlerta::Alerta,
            'origen_type' => 'App\\Models\\User',
            'origen_id' => $destinatario->id,
            'titulo' => 'Alerta sin supervisor',
            'cuerpo' => 'Cuerpo',
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $destinatario->id,
            'estado' => EstadoAlerta::Pendiente,
        ]);

        $this->servicio->escalar($alerta);

        $this->assertEquals(EstadoAlerta::Vencida, $alerta->fresh()->estado);
    }

    // -------------------------------------------------------------------------
    // T-ALS-02 a T-ALS-09 — escenarios exactos del spec
    // -------------------------------------------------------------------------

    /**
     * T-ALS-02 — Alerta con tiempo fijado genera expira_en exactamente 4h laborales después.
     * Tiempo fijado: lunes 09:00 → expira_en = lunes 13:00.
     */
    #[Test]
    public function t_als_02_alerta_expira_exactamente_cuatro_horas_laborales_despues(): void
    {
        $lunes9 = now()->startOfWeek()->setTime(9, 0);
        Carbon::setTestNow($lunes9);

        $usuario = User::factory()->create();

        $alerta = $this->servicio->crear([
            'tipo' => TipoAlerta::Alerta,
            'origen_type' => 'App\\Models\\User',
            'origen_id' => $usuario->id,
            'titulo' => 'Alerta T-ALS-02',
            'cuerpo' => 'Cuerpo',
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $usuario->id,
        ]);

        Carbon::setTestNow(null);

        $esperado = $lunes9->copy()->setTime(13, 0);
        $this->assertEquals($esperado->toDateTimeString(), $alerta->expira_en->toDateTimeString());
    }

    /**
     * T-ALS-05 — No se puede reconocer dos veces la misma alerta.
     * La restricción UNIQUE(alerta_id, usuario_id) impide el segundo registro.
     */
    #[Test]
    public function t_als_05_no_se_puede_reconocer_dos_veces_la_misma_alerta(): void
    {
        $usuario = User::factory()->create();
        $alerta = $this->servicio->crear($this->datosAvisoBásico([
            'tipo' => TipoAlerta::Alerta,
            'destinatario_usuario_id' => $usuario->id,
        ]));

        $this->servicio->reconocer($alerta, $usuario, '127.0.0.1');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->servicio->reconocer($alerta, $usuario, '127.0.0.1');
    }

    /**
     * T-ALS-07 — Escalar sin supervisor registra un warning en el log.
     */
    #[Test]
    public function t_als_07_escalar_sin_supervisor_registra_warning_en_log(): void
    {
        Log::spy();

        $uo = UnidadOrganizativa::create(['nombre' => 'UO Sin Sup Log', 'tipo' => 'servicio', 'activa' => true]);
        $destinatario = User::factory()->create();

        UsuarioUo::create([
            'usuario_id' => $destinatario->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo' => 'adscripcion',
            'fecha_inicio' => now()->toDateString(),
        ]);

        $alerta = Alerta::create([
            'tipo' => TipoAlerta::Alerta,
            'origen_type' => 'App\\Models\\User',
            'origen_id' => $destinatario->id,
            'titulo' => 'Alerta log test',
            'cuerpo' => 'Cuerpo',
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $destinatario->id,
            'estado' => EstadoAlerta::Pendiente,
        ]);

        $this->servicio->escalar($alerta);

        Log::shouldHaveReceived('warning')->once();
        $this->assertEquals(EstadoAlerta::Vencida, $alerta->fresh()->estado);
    }

    /**
     * T-ALS-08 — No existe segundo nivel de escalada.
     * Una alerta ya en estado 'escalada' pasa a 'vencida' sin asignar nuevo escalado.
     */
    #[Test]
    public function t_als_08_no_existe_segundo_nivel_de_escalada(): void
    {
        $uo = UnidadOrganizativa::create(['nombre' => 'UO Doble Esc', 'tipo' => 'servicio', 'activa' => true]);
        $supervisor = User::factory()->create();

        UsuarioUo::create([
            'usuario_id' => $supervisor->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo' => 'adscripcion',
            'fecha_inicio' => now()->toDateString(),
        ]);

        $alerta = Alerta::create([
            'tipo' => TipoAlerta::Alerta,
            'origen_type' => 'App\\Models\\User',
            'origen_id' => $supervisor->id,
            'titulo' => 'Alerta ya escalada',
            'cuerpo' => 'Cuerpo',
            'destinatario_type' => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $supervisor->id,
            'estado' => EstadoAlerta::Escalada,
            'escalada_a_usuario_id' => $supervisor->id,
            'escalada_en' => now()->subHours(5),
        ]);

        $this->servicio->escalar($alerta);

        $alertaActualizada = $alerta->fresh();
        $this->assertEquals(EstadoAlerta::Vencida, $alertaActualizada->estado);
        // El campo escalada_a_usuario_id no se modifica: sigue apuntando al supervisor original
        $this->assertEquals($supervisor->id, $alertaActualizada->escalada_a_usuario_id);
    }

    /**
     * T-ALS-09 — Resolver destinatarios por rol+UO retorna solo los que cumplen ambas condiciones.
     */
    #[Test]
    public function t_als_09_resolver_destinatarios_por_rol_uo(): void
    {
        $uo = UnidadOrganizativa::create(['nombre' => 'UO Rol Test', 'tipo' => 'servicio', 'activa' => true]);

        $ts1 = User::factory()->create();
        $ts2 = User::factory()->create();
        $educador = User::factory()->create();

        foreach ([$ts1, $ts2, $educador] as $usuario) {
            UsuarioUo::create([
                'usuario_id' => $usuario->id,
                'unidad_organizativa_id' => $uo->id,
                'tipo_vinculo' => 'adscripcion',
                'fecha_inicio' => now()->toDateString(),
            ]);
        }

        Role::firstOrCreate(['name' => 'trabajador_social', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'educador_social',   'guard_name' => 'web']);

        $ts1->assignRole('trabajador_social');
        $ts2->assignRole('trabajador_social');
        $educador->assignRole('educador_social');

        $alerta = Alerta::create([
            'tipo' => TipoAlerta::Aviso,
            'origen_type' => 'App\\Models\\User',
            'origen_id' => $ts1->id,
            'titulo' => 'Alerta rol_uo',
            'cuerpo' => 'Cuerpo',
            'destinatario_type' => DestinatarioType::RolUo,
            'destinatario_rol' => 'trabajador_social',
            'destinatario_uo_id' => $uo->id,
            'estado' => EstadoAlerta::Pendiente,
        ]);

        $destinatarios = $this->servicio->resolverDestinatarios($alerta);

        $this->assertCount(2, $destinatarios);
        $this->assertTrue($destinatarios->contains('id', $ts1->id));
        $this->assertTrue($destinatarios->contains('id', $ts2->id));
        $this->assertFalse($destinatarios->contains('id', $educador->id));
    }
}
