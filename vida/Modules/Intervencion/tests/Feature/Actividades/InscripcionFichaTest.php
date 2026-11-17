<?php

namespace Modules\Intervencion\Tests\Feature\Actividades;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Centro\Enums\ViaInscripcion;
use Modules\Centro\Models\InscripcionCentro;
use Modules\Centro\Models\InscripcionSesion;
use Modules\Centro\Models\Prescripcion;
use Modules\Centro\Services\Actividades\InscripcionActividadService;
use Modules\Centro\Tests\Concerns\ActividadesTestSetup;
use Modules\Ciudadania\Http\Livewire\ActividadesPersona;
use Modules\Intervencion\Http\Livewire\PrescribirRecursoModal;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo E — Inscripción desde la ficha y prescripción desde el plan (TF-ACT-23, 25 y 26,
 * y TF-ACT-13 por la interfaz; fase 6, paso 4 y F6-7).
 */
class InscripcionFichaTest extends TestCase
{
    use ActividadesTestSetup;
    use RefreshDatabase;

    /**
     * Escenario de actividades.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarActividades();
    }

    /**
     * TF-ACT-23 — `consulta_basica` adscrita inscribe por acceso libre en su centro y ve el
     * bloque «Actividades», sin la vía de prescripción ni el plan.
     *
     * @return void
     */
    #[Test]
    public function tf_act_23_consulta_basica_inscribe(): void
    {
        $persona = $this->persona($this->tallerCiam);

        Livewire::actingAs($this->auxVallecas)->test(ActividadesPersona::class, ['ciudadanoId' => $persona->id])
            ->assertSee('Inscribir en actividad')
            ->call('abrirInscripcion')
            ->set('actividadId', $this->tallerCiam->id)
            ->set('sesionId', $this->sesion9->id)
            ->call('inscribir')
            ->assertHasNoErrors()
            ->assertSee('Taller de autonomía')
            ->assertSee('Acceso libre')
            ->assertDontSee('Prescribir')
            ->assertDontSee('Plan');

        $inscripcion = InscripcionSesion::where('ciudadano_id', $persona->id)->sole();
        $this->assertSame(ViaInscripcion::Libre, $inscripcion->via);
        $this->assertSame($this->auxVallecas->id, $inscripcion->inscrita_por_id);
    }

    /**
     * F6-4 por la interfaz — Si la actividad exige inscripción en el centro, la ficha la
     * pide antes; sin aceptar, no se inscribe.
     *
     * @return void
     */
    #[Test]
    public function la_ficha_pide_la_inscripcion_en_el_centro(): void
    {
        [$persona] = $this->personaEnCentro($this->ciamVallecas, referencia: $this->psicologaVallecas);

        $componente = Livewire::actingAs($this->psicologaVallecas)->test(ActividadesPersona::class, ['ciudadanoId' => $persona->id])
            ->call('abrirInscripcion')
            ->set('actividadId', $this->tallerCiam->id)
            ->set('sesionId', $this->sesion9->id)
            ->call('inscribir')
            ->assertSet('pideInscripcionCentro', true)
            ->assertSee('¿La inscribes también en el centro?');

        $this->assertSame(0, InscripcionSesion::where('ciudadano_id', $persona->id)->count());

        $componente->call('inscribir', true)->assertHasNoErrors();

        $this->assertTrue(InscripcionCentro::where('ciudadano_id', $persona->id)->activas()->exists());
        $this->assertSame(1, InscripcionSesion::where('ciudadano_id', $persona->id)->vigentes()->count());
    }

    /**
     * TF-ACT-25 — El bloque muestra las inscripciones vigentes y pasadas con actividad,
     * centro, sesión y vía; y permite dar de baja las vigentes.
     *
     * @return void
     */
    #[Test]
    public function tf_act_25_bloque_actividades_de_la_ficha(): void
    {
        $servicio = app(InscripcionActividadService::class);
        $this->prescribir($this->sesion9, $this->ana);
        $charla = $servicio->inscribir($this->sesionCharla, $this->ana, $this->directoraNorte);
        $baja = $servicio->inscribir($this->sesion23, $this->ana, $this->psicologaVallecas);
        $servicio->darDeBaja($baja, 'No puede ese día', $this->psicologaVallecas);

        $componente = Livewire::actingAs($this->tsNorte)->test(ActividadesPersona::class, ['ciudadanoId' => $this->ana->id]);

        $this->assertCount(2, $componente->get('proximas'));
        $this->assertCount(1, $componente->get('anteriores'));

        $componente->assertSee('Taller de autonomía')
            ->assertSee('CIAM Puente de Vallecas')
            ->assertSee('09/11/2026')
            ->assertSee('Prescripción')
            ->assertSee('Charla de prevención')
            ->assertSee('CSS Norte')
            ->assertSee('Acceso libre')
            ->assertSee('Baja: No puede ese día')
            ->call('abrirBaja', $charla->id)
            ->set('motivoBaja', 'Ya no le interesa')
            ->call('confirmarBaja')
            ->assertHasNoErrors();

        $this->assertFalse($charla->fresh()->vigente());
    }

    /**
     * TF-ACT-26 — El selector solo ofrece actividades activas del centro activo con acceso
     * libre o mixto y sesiones con plaza.
     *
     * @return void
     */
    #[Test]
    public function tf_act_26_selector_del_centro_activo(): void
    {
        $persona = $this->persona($this->tallerCiam);
        $soloPrescripcion = $this->tallerCiam->replicate(['prestacion_id'])->fill(['nombre' => 'Grupo terapéutico', 'modo_acceso' => 'prescripcion']);
        $soloPrescripcion->save();
        $this->sesion($soloPrescripcion, '2026-11-12');
        $llena = $this->tallerCiam->replicate(['prestacion_id'])->fill(['nombre' => 'Taller completo', 'aforo_total' => 1, 'aforo_prescripcion' => null, 'modo_acceso' => 'libre']);
        $llena->save();
        $this->llenarLibre($this->sesion($llena, '2026-11-12'), 1);
        $deBaja = $this->tallerCiam->replicate(['prestacion_id'])->fill(['nombre' => 'Taller antiguo', 'activa' => false, 'fecha_baja' => '2026-10-30']);
        $deBaja->save();
        $this->sesion($deBaja, '2026-11-12');

        $componente = Livewire::actingAs($this->psicologaVallecas)->test(ActividadesPersona::class, ['ciudadanoId' => $persona->id])
            ->call('abrirInscripcion');

        $this->assertSame(['Taller de autonomía'], $componente->get('actividadesDisponibles')->pluck('nombre')->all());

        // Sesiones: las futuras con plaza; no la pasada
        $componente->set('actividadId', $this->tallerCiam->id);
        $this->assertSame([$this->sesion9->id, $this->sesion16->id, $this->sesion23->id], $componente->get('sesionesDisponibles')->pluck('id')->all());

        // La psicóloga del CSS Norte (centro activo: CSS Norte) solo ve la charla
        Livewire::actingAs($this->psicologoNorte)->test(ActividadesPersona::class, ['ciudadanoId' => $this->ana->id])
            ->call('abrirInscripcion')
            ->assertSee('Charla de prevención')
            ->assertDontSee('Taller de autonomía');
    }

    /**
     * TF-ACT-13 por la interfaz (F6-7) — Desde la Historia Social de `$ana`, el TS del CSS
     * Norte prescribe la sesión del 9 del taller del CIAM.
     *
     * @return void
     */
    #[Test]
    public function tf_act_13_prescribir_desde_el_modal(): void
    {
        Livewire::actingAs($this->tsNorte)->test(PrescribirRecursoModal::class, ['historiaId' => $this->historiaAna->id])
            ->call('abrir')
            ->assertSee('Actividad grupal (sesión)')
            ->set('tipoRecurso', PrescribirRecursoModal::RECURSO_ACTIVIDAD)
            ->call('avanzar')
            ->assertSee('Taller de autonomía — CIAM Puente de Vallecas')
            ->assertDontSee('Charla de prevención')
            ->set('actividadId', $this->tallerCiam->id)
            ->set('destinoId', $this->sesion9->id)
            ->call('avanzar')
            ->assertSee('4 libres')
            ->call('confirmar')
            ->assertHasErrors('destino')
            ->set('inscribirEnCentro', true)
            ->call('confirmar')
            ->assertHasNoErrors()
            ->assertDispatched('prescripcion-creada');

        $prescripcion = Prescripcion::where('ciudadano_id', $this->ana->id)->sole();
        $this->assertSame('activa', $prescripcion->estado);
        $this->assertSame('sesion_actividad', $prescripcion->tipo_destino);
        $this->assertSame(ViaInscripcion::Prescripcion, InscripcionSesion::where('prescripcion_id', $prescripcion->id)->sole()->via);
    }
}
