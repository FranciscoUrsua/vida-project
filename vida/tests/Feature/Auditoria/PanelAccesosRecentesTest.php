<?php

namespace Tests\Feature\Auditoria;

use App\Models\Audit;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Ciudadania\Http\Livewire\FichaCiudadanoPage;
use Modules\Intervencion\Models\PlanDeIntervencion;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Módulo Auditoría §10.3: Panel de accesos recientes en ficha ciudadano.
 *
 * @see docs/modulo-auditoria.md §10.3
 */
class PanelAccesosRecentesTest extends TestCase
{
    use RefreshDatabase;

    private User $tsr;

    private User $profesional;

    private User $supervisor;

    private Ciudadano $ciudadano;

    private UnidadOrganizativa $uo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->tsr = User::factory()->create();
        $this->tsr->assignRole('intervencion');

        $this->profesional = User::factory()->create();
        $this->profesional->assignRole('intervencion');

        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('supervision');

        $this->ciudadano = Ciudadano::factory()->create();

        $this->uo = UnidadOrganizativa::firstOrCreate(
            ['nombre' => 'UO Panel Accesos Test'],
            ['tipo' => 'centro', 'parent_id' => null, 'activa' => true]
        );

        // El supervisor debe estar adscrito a la UO de la historia para ver todos los accesos
        UsuarioUo::create([
            'usuario_id' => $this->supervisor->id,
            'unidad_organizativa_id' => $this->uo->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today()->toDateString(),
        ]);

        // Asignar $tsr como profesional de referencia del plan de $ciudadano
        $historia = HistoriaSocial::withoutGlobalScopes()->create([
            'ciudadano_id' => $this->ciudadano->id,
            'unidad_organizativa_id' => $this->uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        PlanDeIntervencion::withoutGlobalScopes()->create([
            'historia_id' => $historia->id,
            'tipo' => 'general_asp',
            'profesional_responsable_id' => $this->tsr->id,
            'estado' => 'borrador',
            'fecha_inicio' => today()->toDateString(),
            'objetivos' => 'test',
            'version' => 1,
        ]);
    }

    /**
     * Crea un registro de auditoría directamente sin pasar por el service (sin efectos secundarios).
     */
    private function crearAcceso(User $user, array $overrides = []): Audit
    {
        return Audit::withoutEvents(fn () => Audit::create(array_merge([
            'user_id' => $user->id,
            'accion' => 'ver',
            'auditable_type' => Ciudadano::class,
            'auditable_id' => $this->ciudadano->id,
            'ciudadano_id' => $this->ciudadano->id,
            'ip' => '127.0.0.1',
            'user_agent' => 'Test Browser/1.0',
        ], $overrides)));
    }

    /**
     * TF-AUD-12 — El TSR asignado ve todos los accesos al expediente.
     */
    #[Test]
    public function el_tsr_asignado_ve_todos_los_accesos_al_expediente(): void
    {
        $acceso1 = $this->crearAcceso($this->tsr);
        $acceso2 = $this->crearAcceso($this->profesional);
        $acceso3 = $this->crearAcceso($this->supervisor);

        $component = Livewire::actingAs($this->tsr)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id]);

        $accesos = $component->instance()->actividadReciente;
        $this->assertCount(3, $accesos);
    }

    /**
     * TF-AUD-13 — El supervisor de la UO ve todos los accesos al expediente.
     */
    #[Test]
    public function el_supervisor_de_la_uo_ve_todos_los_accesos_al_expediente(): void
    {
        $this->crearAcceso($this->tsr);
        $this->crearAcceso($this->profesional);
        $this->crearAcceso($this->supervisor);

        $component = Livewire::actingAs($this->supervisor)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id]);

        $accesos = $component->instance()->actividadReciente;
        $this->assertCount(3, $accesos);
    }

    /**
     * TF-AUD-14 — Un profesional no asignado solo ve sus propios accesos.
     */
    #[Test]
    public function un_profesional_no_asignado_solo_ve_sus_propios_accesos(): void
    {
        $this->crearAcceso($this->tsr);
        $this->crearAcceso($this->profesional);

        $component = Livewire::actingAs($this->profesional)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id]);

        $accesos = $component->instance()->actividadReciente;
        $this->assertCount(1, $accesos);
        $this->assertEquals($this->profesional->id, $accesos->first()->user_id);
    }

    /**
     * TF-AUD-15 — El panel muestra como máximo 10 accesos recientes por defecto.
     */
    #[Test]
    public function el_panel_muestra_como_maximo_diez_accesos_recientes_por_defecto(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->crearAcceso($this->tsr, ['created_at' => now()->subMinutes(15 - $i)]);
        }

        $component = Livewire::actingAs($this->tsr)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id]);

        $accesos = $component->instance()->actividadReciente;
        $this->assertCount(10, $accesos);
    }

    /**
     * TF-AUD-16 — El panel no expone la IP ni el user agent en el HTML.
     */
    #[Test]
    public function el_panel_no_expone_ip_ni_user_agent(): void
    {
        $this->crearAcceso($this->tsr, [
            'ip' => '192.168.1.55',
            'user_agent' => 'Mozilla/5.0 SentinelBrowser/99',
        ]);

        $html = Livewire::actingAs($this->tsr)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id])
            ->html();

        $this->assertStringNotContainsString('192.168.1.55', $html);
        $this->assertStringNotContainsString('SentinelBrowser', $html);
    }

    /**
     * TF-AUD-17 — Los accesos propios se presentan con marcador visual diferenciado.
     */
    #[Test]
    public function los_accesos_propios_se_presentan_con_marcador_visual_diferenciado(): void
    {
        $this->crearAcceso($this->tsr);
        $this->crearAcceso($this->profesional);

        $html = Livewire::actingAs($this->tsr)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id])
            ->html();

        // El Blade aplica la clase CSS 'acceso-fila--propio' a los registros propios
        $this->assertStringContainsString('acceso-fila--propio', $html);
    }
}
