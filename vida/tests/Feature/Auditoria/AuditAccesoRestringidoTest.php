<?php

namespace Tests\Feature\Auditoria;

use App\Enums\AccionAuditEnum;
use App\Models\Audit;
use App\Models\Ciudadano;
use App\Models\User;
use App\Services\AuditService;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Módulo Auditoría §10.6: Accesos a ciudadanos protegidos.
 *
 * El flujo completo de autorización de colectivos protegidos (AccesoProtegido,
 * solicitud/denegación/aprobación) se implementa en el Módulo Ciudadanía.
 * Estos tests verifican que el trazado de auditoría con `acceso_restringido`
 * funciona correctamente una vez que el flujo ya ha tomado la decisión.
 *
 * NOTA: Los tests que requieren el flujo completo de autorización de colectivos
 * protegidos están marcados como incompletos hasta que ese flujo esté implementado.
 *
 * @see docs/modulo-auditoria.md §10.6
 * @see docs/modulo-auditoria.md §7
 */
class AuditAccesoRestringidoTest extends TestCase
{
    use RefreshDatabase;

    private User $profesional;

    private Ciudadano $ciudadanoProtegido;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->profesional = User::factory()->create();
        $this->profesional->assignRole('intervencion');

        $this->ciudadanoProtegido = Ciudadano::factory()->create();
    }

    /**
     * TF-AUD-27 — El acceso autorizado a ciudadano protegido se registra con acción acceso_restringido.
     *
     * Verifica que AuditService acepta AccionAuditEnum::AccesoRestringido y persiste
     * el contexto extendido (motivo_declarado, autorizado_por) correctamente.
     */
    #[Test]
    public function el_acceso_autorizado_a_ciudadano_protegido_se_registra_con_accion_acceso_restringido(): void
    {
        $this->actingAs($this->profesional);

        $autorizador = User::factory()->create();
        $autorizador->assignRole('supervision');

        /** @var AuditService $service */
        $service = app(AuditService::class);

        $service->registrarAcceso(
            user: $this->profesional,
            modelo: $this->ciudadanoProtegido,
            accion: AccionAuditEnum::AccesoRestringido,
            ciudadanoId: $this->ciudadanoProtegido->id,
            contexto: [
                'motivo_declarado' => 'Urgencia social declarada por profesional',
                'autorizado_por' => $autorizador->id,
            ],
        );

        $audit = Audit::where('user_id', $this->profesional->id)
            ->where('accion', 'acceso_restringido')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($this->ciudadanoProtegido->id, $audit->ciudadano_id);
        $this->assertArrayHasKey('motivo_declarado', $audit->contexto);
        $this->assertArrayHasKey('autorizado_por', $audit->contexto);
        $this->assertEquals($autorizador->id, $audit->contexto['autorizado_por']);
    }

    /**
     * TF-AUD-28 — El acceso denegado también queda registrado con acceso_restringido.
     *
     * Un acceso denegado también genera traza de auditoría: el profesional intentó
     * acceder pero fue bloqueado. Verificar que el contexto refleja la denegación.
     */
    #[Test]
    public function el_acceso_denegado_a_ciudadano_protegido_tambien_queda_registrado(): void
    {
        $this->actingAs($this->profesional);

        /** @var AuditService $service */
        $service = app(AuditService::class);

        $service->registrarAcceso(
            user: $this->profesional,
            modelo: $this->ciudadanoProtegido,
            accion: AccionAuditEnum::AccesoRestringido,
            ciudadanoId: $this->ciudadanoProtegido->id,
            contexto: [
                'resultado' => 'denegado',
                'motivo_rechazo' => 'Sin autorización de la UO responsable',
            ],
        );

        $audit = Audit::where('user_id', $this->profesional->id)
            ->where('accion', 'acceso_restringido')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('denegado', $audit->contexto['resultado']);

        // El profesional no puede haber visto datos: auditable_id apunta al ciudadano
        // pero el flujo de autorización garantiza que el acceso fue bloqueado antes
        // de servir cualquier dato. Verificar que solo existe este registro (no un 'ver').
        $this->assertDatabaseMissing('audits', [
            'user_id' => $this->profesional->id,
            'accion' => 'ver',
        ]);
    }

    /**
     * TF-AUD-29 — Servicio de urgencia preautorizado genera registro con indicación de emergencia.
     *
     * Cuando un servicio tiene acceso de urgencia preautorizado, el registro debe
     * incluir la indicación del régimen de emergencia en el contexto.
     */
    #[Test]
    public function el_servicio_de_urgencia_preautorizado_genera_registro_con_indicacion_de_emergencia(): void
    {
        $this->actingAs($this->profesional);

        /** @var AuditService $service */
        $service = app(AuditService::class);

        $service->registrarAcceso(
            user: $this->profesional,
            modelo: $this->ciudadanoProtegido,
            accion: AccionAuditEnum::AccesoRestringido,
            ciudadanoId: $this->ciudadanoProtegido->id,
            contexto: [
                'regimen' => 'urgencia_preautorizada',
                'servicio_id' => 1,
                'motivo_declarado' => 'Actuación urgente fuera de horario',
            ],
        );

        $audit = Audit::where('user_id', $this->profesional->id)
            ->where('accion', 'acceso_restringido')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('urgencia_preautorizada', $audit->contexto['regimen']);
        $this->assertArrayHasKey('motivo_declarado', $audit->contexto);
    }
}
