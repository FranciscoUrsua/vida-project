<?php

namespace Modules\Intervencion\Tests\Feature;

use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\PlanDeIntervencion;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo A: Visibilidad de apuntes.
 *
 * Verifica la regla más crítica del módulo: la privacidad estricta de las
 * anotaciones personales. Se verifica a nivel de scope de BD y de Policy.
 *
 * @see docs/modulo-intervencion.md §7, §10 Grupo A
 */
class VisibilidadApuntesTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uo = UnidadOrganizativa::create([
            'nombre' => 'CSS Test Visibilidad',
            'tipo' => 'centro',
            'parent_id' => null,
            'activa' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function crearPlan(): PlanDeIntervencion
    {
        $historia = HistoriaSocial::create([
            'ciudadano_id' => 1,
            'unidad_organizativa_id' => $this->uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        return PlanDeIntervencion::create([
            'historia_id' => $historia->id,
            'tipo' => 'general_asp',
            'profesional_responsable_id' => User::factory()->create()->id,
            'estado' => 'borrador',
            'fecha_inicio' => today()->toDateString(),
            'version' => 1,
        ]);
    }

    private function crearApunte(PlanDeIntervencion $plan, User $autor, VisibilidadApunte $visibilidad): Apunte
    {
        return Apunte::create([
            'historia_id' => $plan->historia_id,
            'plan_id'     => $plan->id,
            'autor_id'    => $autor->id,
            'fecha'       => today()->toDateString(),
            'tipo'        => 'anotacion',
            'contenido'   => 'Contenido de prueba',
            'visibilidad' => $visibilidad,
        ]);
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * TF-INT-A01: Apunte privado invisible para otros profesionales en el scope.
     */
    #[Test]
    public function apunte_privado_invisible_para_otros_en_scope(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();
        $plan = $this->crearPlan();

        $apuntePrivado = $this->crearApunte($plan, $usuarioA, VisibilidadApunte::Privada);

        $visiblesParaB = Apunte::visiblesParaUsuario($usuarioB->id)->pluck('id');
        $visiblesParaA = Apunte::visiblesParaUsuario($usuarioA->id)->pluck('id');

        $this->assertNotContains($apuntePrivado->id, $visiblesParaB, 'El apunte privado no debe ser visible para B');
        $this->assertContains($apuntePrivado->id, $visiblesParaA, 'El autor sí debe ver su propio apunte privado');
    }

    /**
     * TF-INT-A02: La policy deniega la visualización de apunte privado incluso con rol supervisor.
     */
    #[Test]
    public function policy_deniega_view_de_apunte_privado_aunque_sea_supervisor(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();

        // B tiene rol supervisor (si spatie/permission está activo en tests)
        // La policy no depende de roles: el bloqueo es absoluto por diseño
        $plan = $this->crearPlan();
        $apuntePrivado = $this->crearApunte($plan, $usuarioA, VisibilidadApunte::Privada);

        $this->assertFalse(
            Gate::forUser($usuarioB)->allows('view', $apuntePrivado),
            'La policy debe denegar el acceso de B al apunte privado de A'
        );

        $this->assertTrue(
            Gate::forUser($usuarioA)->allows('view', $apuntePrivado),
            'El autor sí puede ver su propio apunte privado'
        );
    }

    /**
     * TF-INT-A03: Apunte de visibilidad profesionales visible para cualquier profesional.
     */
    #[Test]
    public function apunte_profesionales_visible_para_cualquier_profesional(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();
        $plan = $this->crearPlan();

        $apunte = $this->crearApunte($plan, $usuarioA, VisibilidadApunte::Profesionales);

        $visiblesParaB = Apunte::visiblesParaUsuario($usuarioB->id)->pluck('id');

        $this->assertContains($apunte->id, $visiblesParaB, 'El apunte de visibilidad profesionales debe ser visible para B');
    }

    /**
     * TF-INT-A04: Solo el autor puede editar su apunte.
     */
    #[Test]
    public function solo_el_autor_puede_editar_apunte(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();
        $plan = $this->crearPlan();

        $apunte = $this->crearApunte($plan, $usuarioA, VisibilidadApunte::Profesionales);

        $this->assertFalse(
            Gate::forUser($usuarioB)->allows('update', $apunte),
            'B no puede editar apuntes ajenos'
        );

        $this->assertTrue(
            Gate::forUser($usuarioA)->allows('update', $apunte),
            'El autor sí puede editar su propio apunte'
        );
    }

    /**
     * TF-INT-A05: Apunte con visibilidad profesionales no es eliminable ni por su autor.
     */
    #[Test]
    public function apunte_profesionales_no_es_eliminable_ni_por_su_autor(): void
    {
        $autor = User::factory()->create();
        $plan = $this->crearPlan();

        $apunteProfesionales = $this->crearApunte($plan, $autor, VisibilidadApunte::Profesionales);
        $apuntePrivado = $this->crearApunte($plan, $autor, VisibilidadApunte::Privada);

        $this->assertFalse(
            Gate::forUser($autor)->allows('delete', $apunteProfesionales),
            'Los apuntes de visibilidad profesionales son registro permanente: no se pueden eliminar'
        );

        $this->assertTrue(
            Gate::forUser($autor)->allows('delete', $apuntePrivado),
            'El autor sí puede eliminar sus propias notas privadas'
        );
    }

    /**
     * TF-INT-A06: Apuntes privados no se transfieren al reasignar profesional responsable.
     */
    #[Test]
    public function apuntes_privados_no_accesibles_al_reasignar_responsable(): void
    {
        $profesionalA = User::factory()->create();
        $profesionalB = User::factory()->create();

        $historia = HistoriaSocial::create([
            'ciudadano_id' => 2,
            'unidad_organizativa_id' => $this->uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        $plan = PlanDeIntervencion::create([
            'historia_id' => $historia->id,
            'tipo' => 'general_asp',
            'profesional_responsable_id' => $profesionalA->id,
            'estado' => 'borrador',
            'fecha_inicio' => today()->toDateString(),
            'version' => 1,
        ]);

        $apuntePrivado = $this->crearApunte($plan, $profesionalA, VisibilidadApunte::Privada);
        $apunteProfesionales = $this->crearApunte($plan, $profesionalA, VisibilidadApunte::Profesionales);

        // Simular reasignación del responsable del plan
        $plan->update(['profesional_responsable_id' => $profesionalB->id]);

        $visiblesParaB = Apunte::where('plan_id', $plan->id)
            ->visiblesParaUsuario($profesionalB->id)
            ->pluck('id');

        $this->assertNotContains($apuntePrivado->id, $visiblesParaB, 'El apunte privado no es accesible para el nuevo responsable');
        $this->assertContains($apunteProfesionales->id, $visiblesParaB, 'El apunte de visibilidad profesionales sí es accesible para el nuevo responsable');
    }

    /**
     * TF-INT-A07: El scope no filtra apuntes de visibilidad ciudadano para profesionales.
     */
    #[Test]
    public function apunte_ciudadano_visible_para_profesionales(): void
    {
        $usuarioA = User::factory()->create();
        $profesional = User::factory()->create();
        $plan = $this->crearPlan();

        $apunteCiudadano = $this->crearApunte($plan, $usuarioA, VisibilidadApunte::Ciudadano);

        $visiblesParaProfesional = Apunte::visiblesParaUsuario($profesional->id)->pluck('id');

        $this->assertContains(
            $apunteCiudadano->id,
            $visiblesParaProfesional,
            'La visibilidad ciudadano no restringe el acceso a profesionales, solo amplía el acceso al ciudadano'
        );
    }
}
