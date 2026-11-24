<?php

namespace Modules\Intervencion\Tests\Feature\Circuito;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
use App\Services\AmbitoUsuario;
use App\Services\PresentacionPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use Modules\Intervencion\Http\Livewire\PlanPage;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Tests\Concerns\CircuitoTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo B — Acceso del equipo del plan (TF-CIR-08 a 13 y 42; fase 3, F3-3 y F3-4).
 *
 * Restricción de dominio crítica (CLAUDE.md §3): la lectura de la historia por el
 * equipo de un plan no da gestión de la ficha, y una persona protegida solo es
 * visible para su centro, los centros de sus planes en curso y quien tiene rol
 * `intervencion` con esos centros en su ámbito. `supervision` sin `intervencion` no
 * lee ni edita planes (decisión del 14 nov 2026).
 */
class AccesoPlanTest extends TestCase
{
    use CircuitoTestSetup;
    use RefreshDatabase;

    /**
     * Escenario del circuito.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCircuito();
    }

    /** TF-CIR-08 — El equipo especializado gestiona su plan y lee la historia; sin plan, nada. [negativo] */
    #[Test]
    public function tf_cir_08_el_equipo_especializado_gestiona_su_plan(): void
    {
        $apunte = Apunte::create([
            'historia_id' => $this->historiaAna->id, 'autor_id' => $this->tsNorte->id, 'fecha' => '2026-10-15',
            'tipo' => TipoApunte::Anotacion, 'contenido' => 'Entrevista inicial', 'visibilidad' => VisibilidadApunte::Profesionales,
        ]);

        // Sin PIA: no edita nada de Ana ni la ve en los listados.
        $this->assertFalse(Gate::forUser($this->psicologaVallecas)->allows('update', $this->pisoAna));
        $this->assertFalse(Gate::forUser($this->psicologaVallecas)->allows('update', $this->ana));
        $this->assertFalse($this->veEnListados($this->psicologaVallecas, $this->historiaAna));

        $pia = $this->piaDeAna();

        $this->assertTrue(Gate::forUser($this->psicologaVallecas)->allows('view', $pia));
        $this->assertTrue(Gate::forUser($this->psicologaVallecas)->allows('update', $pia));
        $this->assertTrue(Gate::forUser($this->psicologaVallecas)->allows('view', $this->historiaAna));
        $this->assertTrue($this->veEnListados($this->psicologaVallecas, $this->historiaAna));
        $this->assertTrue(Apunte::whereKey($apunte->id)->exists(), 'Lee los apuntes de la historia');
    }

    /** TF-CIR-09 — Leer no es editar la ficha. */
    #[Test]
    public function tf_cir_09_leer_no_es_editar_la_ficha(): void
    {
        $this->piaDeAna();

        $this->assertTrue(Gate::forUser($this->psicologaVallecas)->allows('view', $this->ana));
        $this->assertFalse(Gate::forUser($this->psicologaVallecas)->allows('update', $this->ana));
        $this->assertFalse(Gate::forUser($this->psicologaVallecas)->allows('update', $this->historiaAna));
    }

    /** TF-CIR-10 — El TSR ve los demás planes y no los edita; el psicólogo, su plan y no el PISO. [negativo] */
    #[Test]
    public function tf_cir_10_el_tsr_ve_los_demas_planes_y_no_los_edita(): void
    {
        $pia = $this->piaDeAna();
        $psico = $this->planActivo($this->historiaAna, $this->planPsicologico, $this->cssNorte, $this->psicologoNorte);

        foreach ([$pia, $psico] as $plan) {
            $this->assertTrue(Gate::forUser($this->tsNorte)->allows('view', $plan));
            $this->assertFalse(Gate::forUser($this->tsNorte)->allows('update', $plan));
        }
        $this->assertTrue(Gate::forUser($this->tsNorte)->allows('update', $this->pisoAna));

        // En el servidor: objetivo, seguimiento y cierre rechazados.
        $this->actingAs($this->tsNorte);
        Livewire::test(PlanPage::class, ['plan' => $pia])
            ->set('nuevoObjetivoTexto', 'Objetivo ajeno')
            ->call('guardarObjetivo')
            ->assertForbidden();
        Livewire::test(PlanPage::class, ['plan' => $pia])->call('guardarSeguimiento')->assertForbidden();
        Livewire::test(PlanPage::class, ['plan' => $pia])->set('motivoCierre', 'fin_intervencion')->call('confirmarCierrePlan')->assertForbidden();
        $this->assertSame(0, $pia->objetivos()->count());
        $this->assertSame(EstadoPlan::Activo, $pia->fresh()->estado);

        $this->assertTrue(Gate::forUser($this->psicologoNorte)->allows('update', $psico));
        $this->assertFalse(Gate::forUser($this->psicologoNorte)->allows('update', $this->pisoAna));
    }

    /** TF-CIR-11 — Otro profesional del centro especializado ve y no edita; la directora con `intervencion`, edita. */
    #[Test]
    public function tf_cir_11_otro_profesional_del_centro_especializado(): void
    {
        $pia = $this->piaDeAna();
        $trabajadora = $this->profesionalCon('trabajadora.vallecas', 'intervencion', $this->ciamVallecas, 'Trabajador/a social');

        $this->assertTrue(Gate::forUser($trabajadora)->allows('view', $pia));
        $this->assertFalse(Gate::forUser($trabajadora)->allows('update', $pia));

        // `supervision` sola no lee ni edita planes.
        $this->assertFalse(Gate::forUser($this->directoraVallecas)->allows('view', $pia));
        $this->assertFalse(Gate::forUser($this->directoraVallecas)->allows('update', $pia));

        $this->directoraVallecas->assignRole('intervencion');
        $directora = $this->directoraVallecas->fresh();
        $this->assertTrue(Gate::forUser($directora)->allows('view', $pia));
        $this->assertTrue(Gate::forUser($directora)->allows('update', $pia));
    }

    /** TF-CIR-12 — Plan cerrado, acceso cerrado. */
    #[Test]
    public function tf_cir_12_plan_cerrado_acceso_cerrado(): void
    {
        $pia = $this->piaDeAna();
        $this->assertTrue($this->veEnListados($this->psicologaVallecas, $this->historiaAna));

        $pia->update(['estado' => EstadoPlan::Cerrado, 'fecha_cierre' => '2026-11-02', 'motivo_cierre' => 'fin_intervencion']);
        app(AmbitoUsuario::class)->olvidar();

        $this->assertFalse(app(AmbitoUsuario::class)->leePorPlan($this->psicologaVallecas, $this->historiaAna));
        $this->assertFalse($this->veEnListados($this->psicologaVallecas, $this->historiaAna));
        $this->assertFalse(Gate::forUser($this->psicologaVallecas)->allows('update', $this->historiaAna));
    }

    /** TF-CIR-13 — Persona protegida con plan especializado: la psicóloga la ve; la coordinación sin rol de intervención, no. [negativo] */
    #[Test]
    public function tf_cir_13_persona_protegida_con_plan_especializado(): void
    {
        $this->assertSame(PresentacionPersona::NOMBRE_PROTEGIDO, $this->nombreParaListado($this->psicologaVallecas, $this->marta));

        $pia = $this->planActivo($this->historiaMarta, $this->planPia, $this->ciamVallecas, $this->psicologaVallecas, $this->pisoMarta);
        app(AmbitoUsuario::class)->olvidar();

        $this->assertNotSame(PresentacionPersona::NOMBRE_PROTEGIDO, $this->nombreParaListado($this->psicologaVallecas, $this->marta));
        $this->assertTrue(Gate::forUser($this->psicologaVallecas)->allows('view', $this->historiaMarta));
        $this->assertTrue(Gate::forUser($this->psicologaVallecas)->allows('update', $pia));
        $this->assertSame(PresentacionPersona::NOMBRE_PROTEGIDO, $this->nombreParaListado($this->coordIgualdad, $this->marta));
        $this->assertFalse(Gate::forUser($this->coordIgualdad)->allows('view', $this->historiaMarta));
    }

    /** TF-CIR-42 — Salvedad del rol `intervencion` en UO superior, extendida a los centros de los planes. */
    #[Test]
    public function tf_cir_42_salvedad_del_rol_intervencion_en_uo_superior(): void
    {
        // Sin plan en un centro de su ámbito (la historia es del CSS Norte): no.
        $this->assertFalse(Gate::forUser($this->tecnicaIgualdad)->allows('view', $this->marta));

        $this->planActivo($this->historiaMarta, $this->planPia, $this->ciamVallecas, $this->psicologaVallecas, $this->pisoMarta);
        app(AmbitoUsuario::class)->olvidar();

        $this->assertTrue(Gate::forUser($this->tecnicaIgualdad)->allows('view', $this->marta));
        $this->assertNotSame(PresentacionPersona::NOMBRE_PROTEGIDO, $this->nombreParaListado($this->tecnicaIgualdad, $this->marta));

        $this->tecnicaIgualdad->syncRoles(['supervision']);
        app(AmbitoUsuario::class)->olvidar();
        $tecnica = $this->tecnicaIgualdad->fresh();
        $this->assertFalse(Gate::forUser($tecnica)->allows('view', $this->marta));
        $this->assertSame(PresentacionPersona::NOMBRE_PROTEGIDO, $this->nombreParaListado($tecnica, $this->marta));
    }

    /** TF-CIR-14 — Bloque «Planes de la persona»: PISO primero y PIA; el enlace, solo a quien puede ver el plan. */
    #[Test]
    public function tf_cir_14_bloque_planes_de_la_persona(): void
    {
        $pia = $this->piaDeAna();

        $this->actingAs($this->tsNorte);
        $filas = Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna])->get('planesDeLaPersona');

        $this->assertSame([$this->pisoAna->id, $pia->id], $filas->pluck('plan.id')->all());
        $this->assertTrue($filas[0]['principal']);
        $this->assertSame(['PISO', 'Norte'], [$filas[0]['tipo'], $filas[0]['centro']]);
        $this->assertSame(['PIA', 'Puente de Vallecas', 'Activo'], [$filas[1]['tipo'], $filas[1]['centro'], $filas[1]['estado']]);
        $this->assertSame($this->psicologaVallecas->nombreVisible(), $filas[1]['responsable']);
        $this->assertNotNull($filas[1]['url'], 'El TSR ve el PIA');

        // `supervision` sin `intervencion` no abre la Historia Social desde R2 (TF-STG-21),
        // así que tampoco ve este bloque. [negativo]
        $this->actingAs($this->supNorte);
        app(AmbitoUsuario::class)->olvidar();
        Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna])->assertForbidden();
    }

    /**
     * PIA de Ana en el CIAM de Vallecas, nacido de su PISO, con la psicóloga de responsable.
     *
     * @return PlanDeIntervencion
     */
    private function piaDeAna(): PlanDeIntervencion
    {
        $pia = $this->planActivo($this->historiaAna, $this->planPia, $this->ciamVallecas, $this->psicologaVallecas, $this->pisoAna);
        app(AmbitoUsuario::class)->olvidar();

        return $pia;
    }

    /**
     * Si la historia aparece al usuario en las consultas con scope (listados, timeline).
     *
     * @param User $usuario
     * @param HistoriaSocial $historia
     *
     * @return bool
     */
    private function veEnListados(User $usuario, HistoriaSocial $historia): bool
    {
        $this->actingAs($usuario);
        app(AmbitoUsuario::class)->olvidar();

        return HistoriaSocial::whereKey($historia->id)->exists();
    }

    /**
     * Nombre con que el usuario ve a la persona en un listado.
     *
     * @param User $usuario
     * @param Ciudadano $persona
     *
     * @return string
     */
    private function nombreParaListado(User $usuario, Ciudadano $persona): string
    {
        app(AmbitoUsuario::class)->olvidar();

        return PresentacionPersona::paraListado($persona->fresh(), $usuario)['nombre'];
    }
}
