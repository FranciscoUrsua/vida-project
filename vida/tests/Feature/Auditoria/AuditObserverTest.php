<?php

namespace Tests\Feature\Auditoria;

use App\Models\Audit;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\PlanDeIntervencion;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Módulo Auditoría §10.2: AuditObserver.
 *
 * Verifica que el observer registra automáticamente creaciones, ediciones
 * y eliminaciones en los modelos con el trait Auditable.
 *
 * @see docs/modulo-auditoria.md §10.2
 */
class AuditObserverTest extends TestCase
{
    use RefreshDatabase;

    private User $profesional;

    private PlanDeIntervencion $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->profesional = User::factory()->create();
        $this->profesional->assignRole('intervencion');
        $this->actingAs($this->profesional);

        $ciudadano = Ciudadano::factory()->create();

        $uo = UnidadOrganizativa::firstOrCreate(
            ['nombre' => 'UO Observer Test'],
            ['tipo' => 'centro', 'parent_id' => null, 'activa' => true]
        );

        $historia = HistoriaSocial::withoutGlobalScopes()->create([
            'ciudadano_id' => $ciudadano->id,
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        $this->plan = PlanDeIntervencion::withoutGlobalScopes()->create([
            'historia_id' => $historia->id,
            'tipo' => 'general_asp',
            'profesional_responsable_id' => $this->profesional->id,
            'estado' => 'borrador',
            'fecha_inicio' => today()->toDateString(),
            'objetivos' => 'test',
            'version' => 1,
        ]);
    }

    /**
     * TF-AUD-07 — Registra acción crear con snapshot en datos_despues.
     */
    #[Test]
    public function registra_accion_crear_con_snapshot_en_datos_despues(): void
    {
        $countAntes = Audit::count();

        $apunte = Apunte::create([
            'plan_id' => $this->plan->id,
            'autor_id' => $this->profesional->id,
            'fecha' => today()->toDateString(),
            'tipo' => 'anotacion',
            'contenido' => 'Contenido del apunte de prueba',
            'visibilidad' => 'profesionales',
        ]);

        $this->assertEquals($countAntes + 1, Audit::count());

        $audit = Audit::where('auditable_type', Apunte::class)
            ->where('auditable_id', $apunte->id)
            ->where('accion', 'crear')
            ->first();

        $this->assertNotNull($audit);
        $this->assertNull($audit->datos_antes);
        $this->assertNotNull($audit->datos_despues);
        $this->assertArrayHasKey('contenido', $audit->datos_despues);
        $this->assertEquals('Contenido del apunte de prueba', $audit->datos_despues['contenido']);
    }

    /**
     * TF-AUD-08 — Registra acción editar con diff correcto en datos_antes y datos_despues.
     */
    #[Test]
    public function registra_accion_editar_con_diff_correcto_en_datos_antes_y_datos_despues(): void
    {
        $apunte = Apunte::withoutEvents(fn () => Apunte::create([
            'plan_id' => $this->plan->id,
            'autor_id' => $this->profesional->id,
            'fecha' => today()->toDateString(),
            'tipo' => 'anotacion',
            'contenido' => 'texto original',
            'visibilidad' => 'profesionales',
        ]));

        $countAntes = Audit::count();

        $apunte->update(['contenido' => 'texto modificado']);

        $this->assertEquals($countAntes + 1, Audit::count());

        $audit = Audit::where('auditable_type', Apunte::class)
            ->where('auditable_id', $apunte->id)
            ->where('accion', 'editar')
            ->first();

        $this->assertNotNull($audit);
        $this->assertNotNull($audit->datos_antes);
        $this->assertNotNull($audit->datos_despues);

        // Solo el campo modificado debe aparecer en el diff
        $this->assertEquals('texto original', $audit->datos_antes['contenido']);
        $this->assertEquals('texto modificado', $audit->datos_despues['contenido']);

        // Campos no modificados no aparecen en el diff
        $this->assertArrayNotHasKey('plan_id', $audit->datos_antes);
        $this->assertArrayNotHasKey('plan_id', $audit->datos_despues);
    }

    /**
     * TF-AUD-09 — Registra acción eliminar con snapshot en datos_antes.
     */
    #[Test]
    public function registra_accion_eliminar_con_snapshot_en_datos_antes(): void
    {
        $apunte = Apunte::withoutEvents(fn () => Apunte::create([
            'plan_id' => $this->plan->id,
            'autor_id' => $this->profesional->id,
            'fecha' => today()->toDateString(),
            'tipo' => 'anotacion',
            'contenido' => 'Apunte a eliminar',
            'visibilidad' => 'profesionales',
        ]));

        $apunteId = $apunte->id;
        $countAntes = Audit::count();

        $apunte->delete();

        $this->assertEquals($countAntes + 1, Audit::count());

        $audit = Audit::where('auditable_type', Apunte::class)
            ->where('auditable_id', $apunteId)
            ->where('accion', 'eliminar')
            ->first();

        $this->assertNotNull($audit);
        $this->assertNotNull($audit->datos_antes);
        $this->assertNull($audit->datos_despues);
        $this->assertArrayHasKey('contenido', $audit->datos_antes);
        $this->assertEquals('Apunte a eliminar', $audit->datos_antes['contenido']);
    }

    /**
     * TF-AUD-10 — El snapshot excluye campos que no están en camposAuditables().
     *
     * El método camposAuditables() devuelve por defecto $this->getFillable().
     * Los campos fuera de fillable (como created_at, updated_at) no aparecen
     * en el snapshot aunque estén en los atributos del modelo.
     */
    #[Test]
    public function excluye_los_campos_no_auditables_del_snapshot(): void
    {
        $ciudadano = Ciudadano::factory()->create();

        // Ciudadano usa Auditable; camposAuditables() retorna getFillable()
        // que NO incluye created_at ni updated_at
        $audit = Audit::where('auditable_type', Ciudadano::class)
            ->where('auditable_id', $ciudadano->id)
            ->where('accion', 'crear')
            ->first();

        // El test solo puede verificar esto si hay un usuario autenticado.
        // Aquí el setUp ya hace actingAs, por lo que el observer dispara.
        $this->assertNotNull($audit);
        $this->assertNotNull($audit->datos_despues);

        // Campos fuera de fillable NO deben aparecer en el snapshot
        $this->assertArrayNotHasKey('created_at', $audit->datos_despues);
        $this->assertArrayNotHasKey('updated_at', $audit->datos_despues);
        $this->assertArrayNotHasKey('deleted_at', $audit->datos_despues);

        // Un campo sí fillable SÍ debe estar
        $this->assertArrayHasKey('nombre', $audit->datos_despues);
    }

    /**
     * TF-AUD-11 — El eager loading interno de Eloquent no genera registros de auditoría.
     */
    #[Test]
    public function no_genera_registros_por_eager_loading_interno_de_eloquent(): void
    {
        $apunte = Apunte::withoutEvents(fn () => Apunte::create([
            'plan_id' => $this->plan->id,
            'autor_id' => $this->profesional->id,
            'fecha' => today()->toDateString(),
            'tipo' => 'anotacion',
            'contenido' => 'Test eager loading',
            'visibilidad' => 'profesionales',
        ]));

        $countAntes = Audit::count();

        // Acceder a relaciones no debe generar nuevos audits
        $_ = $apunte->plan;
        $_ = $apunte->plan?->historia;

        $this->assertEquals($countAntes, Audit::count());
    }
}
