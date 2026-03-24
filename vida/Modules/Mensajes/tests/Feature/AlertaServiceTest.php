<?php

namespace Modules\Mensajes\Tests\Feature;

use App\Models\CatalogoSistema;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\EstadoAlerta;
use Modules\Mensajes\Enums\TipoAlerta;
use Modules\Mensajes\Enums\TipoReconocimiento;
use Modules\Mensajes\Models\Alerta;
use Modules\Mensajes\Services\AlertaService;
use Modules\Mensajes\Services\HorarioLaboralService;
use PHPUnit\Framework\Attributes\Test;
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
            'grupo'    => 'sistema.configuracion',
            'clave'    => 'horario_laboral_defecto',
            'etiqueta' => 'Horario laboral por defecto',
            'valor'    => json_encode(['inicio' => '08:00', 'fin' => '17:00', 'dias_semana' => [1, 2, 3, 4, 5]]),
            'orden'    => 1,
            'activo'   => true,
        ]);

        $this->servicio = new AlertaService(new HorarioLaboralService());

        // Crear roles necesarios para los tests de escalada
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
    }

    private function datosAvisoBásico(array $overrides = []): array
    {
        $usuario = User::factory()->create();

        return array_merge([
            'tipo'                   => TipoAlerta::Aviso,
            'origen_type'            => 'App\\Models\\User',
            'origen_id'              => $usuario->id,
            'titulo'                 => 'Aviso de prueba',
            'cuerpo'                 => 'Cuerpo del aviso',
            'destinatario_type'      => DestinatarioType::Usuario,
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
            'tipo'                   => TipoAlerta::Alerta,
            'origen_type'            => 'App\\Models\\User',
            'origen_id'              => $usuario->id,
            'titulo'                 => 'Alerta urgente',
            'cuerpo'                 => 'Requiere reconocimiento',
            'destinatario_type'      => DestinatarioType::Usuario,
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
        $alerta  = $this->servicio->crear($this->datosAvisoBásico([
            'tipo'                   => TipoAlerta::Alerta,
            'destinatario_usuario_id' => $usuario->id,
        ]));

        $this->servicio->reconocer($alerta, $usuario, '127.0.0.1');

        $this->assertEquals(EstadoAlerta::Reconocida, $alerta->fresh()->estado);
        $this->assertDatabaseHas('alerta_reconocimientos', [
            'alerta_id'  => $alerta->id,
            'usuario_id' => $usuario->id,
            'tipo'       => TipoReconocimiento::Reconocida->value,
        ]);
    }

    #[Test]
    public function descartar_aviso_registra_tipo_descartada(): void
    {
        $usuario = User::factory()->create();
        $aviso   = $this->servicio->crear($this->datosAvisoBásico([
            'destinatario_usuario_id' => $usuario->id,
        ]));

        $this->servicio->reconocer($aviso, $usuario, '127.0.0.1');

        $this->assertDatabaseHas('alerta_reconocimientos', [
            'alerta_id' => $aviso->id,
            'tipo'      => TipoReconocimiento::Descartada->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // Escalada
    // -------------------------------------------------------------------------

    #[Test]
    public function escalar_alerta_con_supervisor_cambia_estado_a_escalada(): void
    {
        // Crear UO, usuario destinatario y supervisor con rol 'supervisor'
        $uo          = UnidadOrganizativa::create(['nombre' => 'UO Test', 'tipo' => 'servicio', 'activa' => true]);
        $destinatario = User::factory()->create();
        $supervisor   = User::factory()->create();

        UsuarioUo::create([
            'usuario_id'              => $destinatario->id,
            'unidad_organizativa_id'  => $uo->id,
            'tipo_vinculo'            => 'adscripcion',
            'fecha_inicio'            => now()->toDateString(),
        ]);

        UsuarioUo::create([
            'usuario_id'              => $supervisor->id,
            'unidad_organizativa_id'  => $uo->id,
            'tipo_vinculo'            => 'adscripcion',
            'fecha_inicio'            => now()->toDateString(),
        ]);

        // Asignar rol supervisor via Spatie
        $supervisor->assignRole('supervisor');

        $alerta = Alerta::create([
            'tipo'                   => TipoAlerta::Alerta,
            'origen_type'            => 'App\\Models\\User',
            'origen_id'              => $destinatario->id,
            'titulo'                 => 'Alerta vencida',
            'cuerpo'                 => 'Vencida sin reconocer',
            'destinatario_type'      => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $destinatario->id,
            'estado'                 => EstadoAlerta::Pendiente,
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
        $uo           = UnidadOrganizativa::create(['nombre' => 'UO Sin Sup', 'tipo' => 'servicio', 'activa' => true]);
        $destinatario  = User::factory()->create();

        UsuarioUo::create([
            'usuario_id'             => $destinatario->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo'           => 'adscripcion',
            'fecha_inicio'           => now()->toDateString(),
        ]);

        $alerta = Alerta::create([
            'tipo'                   => TipoAlerta::Alerta,
            'origen_type'            => 'App\\Models\\User',
            'origen_id'              => $destinatario->id,
            'titulo'                 => 'Alerta sin supervisor',
            'cuerpo'                 => 'Cuerpo',
            'destinatario_type'      => DestinatarioType::Usuario,
            'destinatario_usuario_id' => $destinatario->id,
            'estado'                 => EstadoAlerta::Pendiente,
        ]);

        $this->servicio->escalar($alerta);

        $this->assertEquals(EstadoAlerta::Vencida, $alerta->fresh()->estado);
    }
}
