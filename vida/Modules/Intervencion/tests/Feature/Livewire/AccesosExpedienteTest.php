<?php

namespace Modules\Intervencion\Tests\Feature\Livewire;

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
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Enums\TipoPlan;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use Modules\Intervencion\Models\PlanDeIntervencion;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales del widget de últimos accesos al expediente.
 *
 * Cubre ambas superficies:
 *   - CiudadanoPage (Intervencion/Livewire): TF-AUD-INT-01 a TF-AUD-INT-09
 *   - FichaCiudadanoPage (Ciudadania/Livewire): TF-AUD-INT-10 y TF-AUD-INT-11
 *
 * @see docs/instrucciones-cli/ui-intervencion-accesos-auditoria.md §6
 */
class AccesosExpedienteTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uoPrincipal;

    private UnidadOrganizativa $uoOtra;

    private User $tsr;

    private User $otroTSR;

    private User $supervisor;

    private User $supervisorOtraUo;

    private Ciudadano $ciudadano;

    private HistoriaSocial $historia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->uoPrincipal = UnidadOrganizativa::create([
            'nombre' => 'CSS Accesos Test Principal',
            'tipo' => 'centro',
            'activa' => true,
        ]);

        $this->uoOtra = UnidadOrganizativa::create([
            'nombre' => 'CSS Accesos Test Otra',
            'tipo' => 'centro',
            'activa' => true,
        ]);

        // TSR: profesional responsable del plan, adscrito a uoPrincipal
        $this->tsr = User::factory()->create();
        $this->tsr->assignRole('intervencion');
        UsuarioUo::create([
            'usuario_id' => $this->tsr->id,
            'unidad_organizativa_id' => $this->uoPrincipal->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today()->toDateString(),
        ]);

        // Profesional de otra UO (no asignado como TSR)
        $this->otroTSR = User::factory()->create();
        $this->otroTSR->assignRole('intervencion');
        UsuarioUo::create([
            'usuario_id' => $this->otroTSR->id,
            'unidad_organizativa_id' => $this->uoOtra->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today()->toDateString(),
        ]);

        // Supervisor en uoPrincipal
        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('supervision');
        UsuarioUo::create([
            'usuario_id' => $this->supervisor->id,
            'unidad_organizativa_id' => $this->uoPrincipal->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today()->toDateString(),
        ]);

        // Supervisor en uoOtra (no tiene acceso a accesos de uoPrincipal)
        $this->supervisorOtraUo = User::factory()->create();
        $this->supervisorOtraUo->assignRole('supervision');
        UsuarioUo::create([
            'usuario_id' => $this->supervisorOtraUo->id,
            'unidad_organizativa_id' => $this->uoOtra->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today()->toDateString(),
        ]);

        $this->ciudadano = Ciudadano::factory()->create();

        $this->historia = HistoriaSocial::withoutGlobalScopes()->create([
            'ciudadano_id' => $this->ciudadano->id,
            'unidad_organizativa_id' => $this->uoPrincipal->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        PlanDeIntervencion::withoutGlobalScopes()->create([
            'historia_id' => $this->historia->id,
            'tipo' => TipoPlan::GeneralAsp,
            'profesional_responsable_id' => $this->tsr->id,
            'estado' => EstadoPlan::Activo,
            'fecha_inicio' => today()->toDateString(),
            'objetivos' => 'test',
            'version' => 1,
        ]);
    }

    /**
     * Crea un registro de auditoría directamente (sin efectos secundarios del observer).
     *
     * @param array<string, mixed> $overrides
     */
    private function crearAcceso(User $user, array $overrides = []): Audit
    {
        return Audit::withoutEvents(function () use ($user, $overrides): Audit {
            $audit = new Audit;
            $audit->forceFill(array_merge([
                'user_id' => $user->id,
                'accion' => 'ver',
                'auditable_type' => Ciudadano::class,
                'auditable_id' => $this->ciudadano->id,
                'ciudadano_id' => $this->ciudadano->id,
                'ip' => '127.0.0.1',
                'user_agent' => 'Test Browser/1.0',
            ], $overrides));
            $audit->save();

            return $audit;
        });
    }

    // =========================================================================
    // CiudadanoPage (pantalla de intervención)
    // =========================================================================

    /**
     * TF-AUD-INT-01 — El TSR asignado ve todos los accesos al expediente.
     */
    #[Test]
    public function el_tsr_asignado_ve_todos_los_accesos_en_ciudadano_page(): void
    {
        $this->crearAcceso($this->tsr);
        $this->crearAcceso($this->otroTSR);
        $this->crearAcceso($this->supervisor);

        $accesos = Livewire::actingAs($this->tsr)
            ->test(CiudadanoPage::class, ['historia' => $this->historia])
            ->get('accesosRecientes');

        $this->assertCount(3, $accesos);
    }

    /**
     * TF-AUD-INT-02 — El supervisor de la UO ve todos los accesos al expediente.
     */
    #[Test]
    public function el_supervisor_de_la_uo_ve_todos_los_accesos_en_ciudadano_page(): void
    {
        $this->crearAcceso($this->tsr);
        $this->crearAcceso($this->otroTSR);

        $accesos = Livewire::actingAs($this->supervisor)
            ->test(CiudadanoPage::class, ['historia' => $this->historia])
            ->get('accesosRecientes');

        $this->assertCount(2, $accesos);
    }

    /**
     * TF-AUD-INT-03 — Un profesional de otra UO solo ve sus propios accesos.
     */
    #[Test]
    public function un_profesional_de_otra_uo_solo_ve_sus_propios_accesos(): void
    {
        $this->crearAcceso($this->tsr);
        $this->crearAcceso($this->otroTSR);

        $accesos = Livewire::actingAs($this->otroTSR)
            ->test(CiudadanoPage::class, ['historia' => $this->historia])
            ->get('accesosRecientes');

        $this->assertCount(1, $accesos);
        $this->assertEquals($this->otroTSR->id, $accesos->first()->user_id);
    }

    /**
     * TF-AUD-INT-04 — Un supervisor de otra UO solo ve sus propios accesos.
     */
    #[Test]
    public function un_supervisor_de_otra_uo_solo_ve_sus_propios_accesos(): void
    {
        $this->crearAcceso($this->tsr);
        $this->crearAcceso($this->supervisorOtraUo);

        $accesos = Livewire::actingAs($this->supervisorOtraUo)
            ->test(CiudadanoPage::class, ['historia' => $this->historia])
            ->get('accesosRecientes');

        $this->assertCount(1, $accesos);
        $this->assertEquals($this->supervisorOtraUo->id, $accesos->first()->user_id);
    }

    /**
     * TF-AUD-INT-05 — Accesos de otra UO con acción 'ver' tienen clase CSS 'acceso-fila--sospechoso'.
     */
    #[Test]
    public function acceso_de_otra_uo_con_accion_ver_tiene_clase_sospechoso(): void
    {
        // Acceso de otra UO, acción 'ver' (solo lectura)
        $this->crearAcceso($this->otroTSR, [
            'accion' => 'ver',
            'contexto' => ['unidad_organizativa_id' => $this->uoOtra->id],
        ]);

        $html = Livewire::actingAs($this->tsr)
            ->test(CiudadanoPage::class, ['historia' => $this->historia])
            ->html();

        $this->assertStringContainsString('acceso-fila--sospechoso', $html);
        $this->assertStringNotContainsString('acceso-fila--anomalo', $html);
    }

    /**
     * TF-AUD-INT-06 — Accesos de otra UO con acción 'editar' tienen clase CSS 'acceso-fila--anomalo'.
     */
    #[Test]
    public function acceso_de_otra_uo_con_accion_editar_tiene_clase_anomalo(): void
    {
        // Acceso de otra UO, acción 'editar' (modificación)
        $this->crearAcceso($this->otroTSR, [
            'accion' => 'editar',
            'contexto' => ['unidad_organizativa_id' => $this->uoOtra->id],
        ]);

        $html = Livewire::actingAs($this->tsr)
            ->test(CiudadanoPage::class, ['historia' => $this->historia])
            ->html();

        $this->assertStringContainsString('acceso-fila--anomalo', $html);
        $this->assertStringContainsString('alert-triangle', $html);
    }

    /**
     * TF-AUD-INT-07 — Los accesos propios tienen clase CSS 'acceso-fila--propio'.
     */
    #[Test]
    public function accesos_propios_tienen_clase_propio(): void
    {
        $this->crearAcceso($this->tsr, ['accion' => 'ver']);

        $html = Livewire::actingAs($this->tsr)
            ->test(CiudadanoPage::class, ['historia' => $this->historia])
            ->html();

        $this->assertStringContainsString('acceso-fila--propio', $html);
    }

    /**
     * TF-AUD-INT-08 — El widget muestra como máximo 5 accesos en la vista de intervención.
     */
    #[Test]
    public function el_widget_muestra_como_maximo_5_accesos(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->crearAcceso($this->tsr, ['created_at' => now()->subMinutes(8 - $i)]);
        }

        $accesos = Livewire::actingAs($this->tsr)
            ->test(CiudadanoPage::class, ['historia' => $this->historia])
            ->get('accesosRecientes');

        $this->assertCount(5, $accesos);
    }

    /**
     * TF-AUD-INT-09 — El widget no expone IP ni user_agent en el HTML renderizado.
     */
    #[Test]
    public function el_widget_no_expone_ip_ni_user_agent(): void
    {
        $this->crearAcceso($this->tsr, [
            'ip' => '10.20.30.40',
            'user_agent' => 'Mozilla/5.0 SecretAgent/99',
        ]);

        $html = Livewire::actingAs($this->tsr)
            ->test(CiudadanoPage::class, ['historia' => $this->historia])
            ->html();

        $this->assertStringNotContainsString('10.20.30.40', $html);
        $this->assertStringNotContainsString('SecretAgent', $html);
    }

    // =========================================================================
    // FichaCiudadanoPage (widget en la ficha del ciudadano)
    // =========================================================================

    /**
     * TF-AUD-INT-10 — El widget de la ficha del ciudadano aplica la misma lógica de visibilidad.
     * Un profesional de otra UO solo ve sus propios accesos.
     */
    #[Test]
    public function el_widget_ficha_aplica_la_misma_logica_de_visibilidad(): void
    {
        $this->crearAcceso($this->tsr);
        $this->crearAcceso($this->otroTSR);

        $accesos = Livewire::actingAs($this->otroTSR)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id])
            ->get('actividadReciente');

        $this->assertCount(1, $accesos);
        $this->assertEquals($this->otroTSR->id, $accesos->first()->user_id);
    }

    /**
     * TF-AUD-INT-11 — El widget de la ficha del ciudadano solo es visible para roles
     * intervencion, supervision y adm_sistema. Un usuario con rol tramitacion no lo ve.
     */
    #[Test]
    public function el_widget_ficha_no_es_visible_para_rol_tramitacion(): void
    {
        // Crear un acceso del propio usuario para que haya datos en la BD
        $usuarioTramitacion = User::factory()->create();
        $usuarioTramitacion->assignRole('tramitacion');
        $this->crearAcceso($usuarioTramitacion);

        $html = Livewire::actingAs($usuarioTramitacion)
            ->test(FichaCiudadanoPage::class, ['ciudadano' => $this->ciudadano->id])
            ->html();

        $this->assertStringNotContainsString('accesos-panel', $html);
        $this->assertStringNotContainsString('Últimos accesos', $html);
    }
}
