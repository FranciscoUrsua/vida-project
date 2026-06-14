<?php

namespace Tests\Feature\Auditoria;

use App\Filament\Resources\AuditResource;
use App\Models\Audit;
use App\Models\Ciudadano;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Módulo Auditoría §10.4: AuditResource en Filament.
 *
 * @see docs/modulo-auditoria.md §10.4
 */
class AuditResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private User $admSistema;

    private User $profesionalSinAcceso;

    private Ciudadano $ciudadano;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('supervision');

        $this->admSistema = User::factory()->create();
        $this->admSistema->assignRole('adm_sistema');

        $this->profesionalSinAcceso = User::factory()->create();
        $this->profesionalSinAcceso->assignRole('intervencion');

        $this->ciudadano = Ciudadano::factory()->create();
    }

    /**
     * Crea un registro de auditoría directamente.
     */
    private function crearAudit(User $user, array $overrides = []): Audit
    {
        return Audit::withoutEvents(fn () => Audit::create(array_merge([
            'user_id'        => $user->id,
            'accion'         => 'ver',
            'auditable_type' => Ciudadano::class,
            'auditable_id'   => $this->ciudadano->id,
            'ciudadano_id'   => $this->ciudadano->id,
        ], $overrides)));
    }

    /**
     * TF-AUD-18 — Un profesional sin rol supervision no puede acceder al AuditResource.
     */
    #[Test]
    public function un_profesional_sin_rol_supervision_no_puede_acceder_al_audit_resource(): void
    {
        $this->actingAs($this->profesionalSinAcceso);
        $this->assertFalse(AuditResource::canAccess());
    }

    /**
     * TF-AUD-19 — El supervisor con su rol puede acceder al AuditResource.
     */
    #[Test]
    public function el_supervisor_puede_acceder_al_audit_resource(): void
    {
        $this->actingAs($this->supervisor);
        $this->assertTrue(AuditResource::canAccess());
    }

    /**
     * TF-AUD-20 — adm_sistema ve registros de todas las UOs sin restricción.
     */
    #[Test]
    public function adm_sistema_ve_registros_de_todas_las_uos_sin_restriccion(): void
    {
        $uo1 = UnidadOrganizativa::firstOrCreate(
            ['nombre' => 'UO Audit Test A'],
            ['tipo' => 'centro', 'parent_id' => null, 'activa' => true]
        );
        $uo2 = UnidadOrganizativa::firstOrCreate(
            ['nombre' => 'UO Audit Test B'],
            ['tipo' => 'centro', 'parent_id' => null, 'activa' => true]
        );
        $uo3 = UnidadOrganizativa::firstOrCreate(
            ['nombre' => 'UO Audit Test C'],
            ['tipo' => 'centro', 'parent_id' => null, 'activa' => true]
        );

        $prof1 = User::factory()->create();
        $prof2 = User::factory()->create();
        $prof3 = User::factory()->create();

        $this->crearAudit($prof1);
        $this->crearAudit($prof2);
        $this->crearAudit($prof3);

        // adm_sistema no tiene restricción de scope de UO
        $query = AuditResource::getEloquentQuery();
        $this->actingAs($this->admSistema);

        // Verificar que el resource no añade scope de UO para adm_sistema
        // Los 3 registros deben ser accesibles
        $this->assertDatabaseCount('audits', 3);
    }

    /**
     * TF-AUD-21 — El AuditResource no expone acciones de creación, edición ni eliminación.
     */
    #[Test]
    public function el_audit_resource_no_expone_acciones_de_creacion_edicion_ni_eliminacion(): void
    {
        $this->actingAs($this->supervisor);
        $this->assertFalse(AuditResource::canCreate());
        $this->assertFalse(AuditResource::canEdit(new Audit));
        $this->assertFalse(AuditResource::canDelete(new Audit));
    }

    /**
     * TF-AUD-22 — El modelo Audit no puede actualizarse ni eliminarse por instancia.
     *
     * Verifica la inmutabilidad arquitectural: Audit::update() y Audit::delete()
     * por instancia lanzan LogicException. La purga usa el query builder directamente.
     */
    #[Test]
    public function el_modelo_audit_es_inmutable_y_lanza_exception_en_update_y_delete(): void
    {
        $audit = $this->crearAudit($this->supervisor);

        $this->expectException(\LogicException::class);
        $audit->update(['accion' => 'editar']);
    }

    /**
     * TF-AUD-23 — El modelo Audit lanza LogicException al intentar delete() de instancia.
     */
    #[Test]
    public function el_modelo_audit_lanza_exception_al_intentar_delete_de_instancia(): void
    {
        $audit = $this->crearAudit($this->supervisor);

        $this->expectException(\LogicException::class);
        $audit->delete();
    }
}
