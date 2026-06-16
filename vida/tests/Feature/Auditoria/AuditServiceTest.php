<?php

namespace Tests\Feature\Auditoria;

use App\Enums\AccionAuditEnum;
use App\Models\Audit;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Services\AuditService;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\PlanDeIntervencion;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Módulo Auditoría §10.1: AuditService.
 *
 * @see docs/modulo-auditoria.md §10.1
 */
class AuditServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuditService $service;

    private User $profesional;

    private Ciudadano $ciudadano;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->service = app(AuditService::class);

        $this->profesional = User::factory()->create();
        $this->profesional->assignRole('intervencion');

        $this->ciudadano = Ciudadano::factory()->create();
    }

    /**
     * TF-AUD-01 — Registra un acceso de lectura con todos los campos obligatorios rellenos.
     */
    #[Test]
    public function registra_un_acceso_de_lectura_con_todos_los_campos_obligatorios_rellenos(): void
    {
        $this->actingAs($this->profesional);

        $this->service->registrarAcceso(
            user: $this->profesional,
            modelo: $this->ciudadano,
            accion: AccionAuditEnum::Ver,
        );

        $this->assertDatabaseHas('audits', [
            'user_id' => $this->profesional->id,
            'accion' => 'ver',
            'auditable_type' => Ciudadano::class,
            'auditable_id' => $this->ciudadano->id,
            'ciudadano_id' => $this->ciudadano->id,
        ]);

        $audit = Audit::where('user_id', $this->profesional->id)->latest('created_at')->first();
        $this->assertNotNull($audit->ip);
        $this->assertNotNull($audit->created_at);
    }

    /**
     * TF-AUD-02 — Resuelve ciudadano_id desde el modelo cuando no se pasa explícitamente.
     */
    #[Test]
    public function resuelve_ciudadano_id_desde_el_modelo_cuando_no_se_pasa_explicitamente(): void
    {
        $this->actingAs($this->profesional);

        $uo = UnidadOrganizativa::firstOrCreate(
            ['nombre' => 'UO Test Auditoria'],
            ['tipo' => 'centro', 'parent_id' => null, 'activa' => true]
        );

        $historia = HistoriaSocial::withoutGlobalScopes()->create([
            'ciudadano_id' => $this->ciudadano->id,
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        $plan = PlanDeIntervencion::withoutGlobalScopes()->create([
            'historia_id' => $historia->id,
            'tipo' => 'general_asp',
            'profesional_responsable_id' => $this->profesional->id,
            'estado' => 'borrador',
            'fecha_inicio' => today()->toDateString(),
            'objetivos' => 'test',
            'version' => 1,
        ]);

        $apunte = Apunte::withoutEvents(fn () => Apunte::create([
            'plan_id' => $plan->id,
            'autor_id' => $this->profesional->id,
            'fecha' => today()->toDateString(),
            'tipo' => 'anotacion',
            'contenido' => 'Test',
            'visibilidad' => 'profesionales',
        ]));

        $auditCount = Audit::count();

        $this->service->registrarAcceso(
            user: $this->profesional,
            modelo: $apunte,
            accion: AccionAuditEnum::Ver,
        );

        $this->assertDatabaseCount('audits', $auditCount + 1);
        $this->assertDatabaseHas('audits', [
            'auditable_type' => Apunte::class,
            'auditable_id' => $apunte->id,
            'ciudadano_id' => $this->ciudadano->id,
        ]);
    }

    /**
     * TF-AUD-03 — El ciudadano_id explícito tiene prioridad sobre el resuelto por el modelo.
     */
    #[Test]
    public function usa_el_ciudadano_id_explicito_con_prioridad_sobre_el_resuelto_por_el_modelo(): void
    {
        $this->actingAs($this->profesional);

        // Segundo ciudadano distinto del que getCiudadanoId() resolvería
        $otroCiudadano = Ciudadano::factory()->create();

        $uo = UnidadOrganizativa::firstOrCreate(
            ['nombre' => 'UO Test Auditoria Prio'],
            ['tipo' => 'centro', 'parent_id' => null, 'activa' => true]
        );

        $historia = HistoriaSocial::withoutGlobalScopes()->create([
            'ciudadano_id' => $this->ciudadano->id,
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        $plan = PlanDeIntervencion::withoutGlobalScopes()->create([
            'historia_id' => $historia->id,
            'tipo' => 'general_asp',
            'profesional_responsable_id' => $this->profesional->id,
            'estado' => 'borrador',
            'fecha_inicio' => today()->toDateString(),
            'objetivos' => 'test',
            'version' => 1,
        ]);

        $apunte = Apunte::withoutEvents(fn () => Apunte::create([
            'plan_id' => $plan->id,
            'autor_id' => $this->profesional->id,
            'fecha' => today()->toDateString(),
            'tipo' => 'anotacion',
            'contenido' => 'Test',
            'visibilidad' => 'profesionales',
        ]));

        // Se pasa explícitamente el ID del otro ciudadano, que tiene prioridad
        $this->service->registrarAcceso(
            user: $this->profesional,
            modelo: $apunte,
            accion: AccionAuditEnum::Ver,
            ciudadanoId: $otroCiudadano->id,
        );

        $this->assertDatabaseHas('audits', [
            'auditable_type' => Apunte::class,
            'auditable_id' => $apunte->id,
            'ciudadano_id' => $otroCiudadano->id,
        ]);
    }

    /**
     * TF-AUD-04 — Enriquece el contexto con canal=web y ruta actual automáticamente.
     *
     * El contexto se enriquece dentro del ciclo de vida de un request HTTP.
     * Aquí simulamos una ruta real que llama al servicio para verificar que
     * el contexto incluye canal y ruta cuando existe un request activo con sesión.
     */
    #[Test]
    public function enriquece_el_contexto_con_canal_web_y_ruta_actual_automaticamente(): void
    {
        $ciudadanoId = $this->ciudadano->id;
        $profesionalId = $this->profesional->id;

        Route::get('/test-audit-contexto', function () use ($ciudadanoId, $profesionalId) {
            $ciudadano = Ciudadano::withoutGlobalScopes()->find($ciudadanoId);
            $profesional = User::find($profesionalId);
            app(AuditService::class)->registrarAcceso(
                user: $profesional,
                modelo: $ciudadano,
            );

            return 'ok';
        });

        $this->actingAs($this->profesional)->get('/test-audit-contexto');

        $audit = Audit::where('user_id', $this->profesional->id)->latest('created_at')->first();
        $this->assertNotNull($audit);
        $this->assertIsArray($audit->contexto);
        $this->assertEquals('web', $audit->contexto['canal'] ?? null);
        $this->assertArrayHasKey('ruta', $audit->contexto);
        $this->assertEquals('test-audit-contexto', $audit->contexto['ruta']);
    }

    /**
     * TF-AUD-05 — El observer omite el registro si no hay usuario autenticado.
     */
    #[Test]
    public function omite_el_registro_si_no_hay_usuario_autenticado_y_no_se_fuerza_actuante(): void
    {
        // Sin actingAs — contexto de consola sin usuario autenticado
        $countAntes = Audit::count();

        // El observer solo actúa en modelos Auditable. Ciudadano usa Auditable.
        // Sin usuario autenticado, el observer debe salir sin crear registro.
        $ciudadano = Ciudadano::factory()->create();

        $this->assertEquals($countAntes, Audit::count());
    }

    /**
     * TF-AUD-06 — Leer registros de audits no genera nuevos registros de auditoría.
     */
    #[Test]
    public function no_genera_registro_de_auditoria_al_leer_registros_de_la_tabla_audits(): void
    {
        $this->actingAs($this->profesional);

        // Crear algunos registros de auditoría directamente
        Audit::withoutEvents(fn () => Audit::create([
            'user_id' => $this->profesional->id,
            'accion' => 'ver',
            'auditable_type' => Ciudadano::class,
            'auditable_id' => $this->ciudadano->id,
            'ciudadano_id' => $this->ciudadano->id,
        ]));

        $countAntes = Audit::count();

        // Leer desde la tabla de auditoría no debe generar nuevos registros
        $lista = Audit::where('ciudadano_id', $this->ciudadano->id)->get();

        $this->assertEquals($countAntes, Audit::count());
        $this->assertCount(1, $lista);
    }
}
