<?php

namespace Modules\Centro\Tests\Feature\Actividades;

use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Centro\Enums\EstadoInscripcionSesion;
use Modules\Centro\Enums\ViaInscripcion;
use Modules\Centro\Models\Actividad;
use Modules\Centro\Models\InscripcionCentro;
use Modules\Centro\Models\InscripcionSesion;
use Modules\Centro\Services\Actividades\InscripcionActividadService;
use Modules\Centro\Services\Actividades\InscripcionCentroRequerida;
use Modules\Centro\Tests\Concerns\ActividadesTestSetup;
use Modules\Prestaciones\Enums\EstadoPrestacionPersona;
use Modules\Prestaciones\Models\PrestacionPersona;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo B — Inscripción por acceso libre (TF-ACT-05 a 12; fase 6, F6-1, F6-4, F6-5 y F6-10).
 */
class InscripcionLibreTest extends TestCase
{
    use ActividadesTestSetup;
    use RefreshDatabase;

    private InscripcionActividadService $servicio;

    /**
     * Escenario de actividades.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarActividades();
        $this->servicio = app(InscripcionActividadService::class);
    }

    /**
     * TF-ACT-05 — Inscripción libre con quién y cuándo.
     *
     * @return void
     */
    #[Test]
    public function tf_act_05_inscribir(): void
    {
        $persona = $this->persona($this->tallerCiam);

        $inscripcion = $this->servicio->inscribir($this->sesion9, $persona, $this->psicologaVallecas);

        $this->assertSame(ViaInscripcion::Libre, $inscripcion->via);
        $this->assertSame(EstadoInscripcionSesion::Inscrita, $inscripcion->estado);
        $this->assertSame($this->psicologaVallecas->id, $inscripcion->inscrita_por_id);
        $this->assertEquals(now(), $inscripcion->created_at);
        $this->assertNull($inscripcion->prescripcion_id);
    }

    /**
     * TF-ACT-06 — Si la actividad exige inscripción en el centro, primero se ofrece; aceptada,
     * se crean las dos; rechazada, ninguna (F6-4).
     *
     * @return void
     */
    #[Test]
    public function tf_act_06_inscripcion_en_el_centro(): void
    {
        try {
            $this->servicio->inscribir($this->sesion9, $this->ana, $this->psicologaVallecas);
            $this->fail('Se ha inscrito sin la inscripción en el centro.');
        } catch (InscripcionCentroRequerida $e) {
            $this->assertTrue($e->centro->is($this->ciamVallecas));
        }

        $this->assertSame(0, InscripcionSesion::where('ciudadano_id', $this->ana->id)->count());
        $this->assertSame(0, InscripcionCentro::where('ciudadano_id', $this->ana->id)->count());

        $this->servicio->inscribir($this->sesion9, $this->ana, $this->psicologaVallecas, inscribirEnCentro: true);

        $this->assertTrue(InscripcionCentro::where('ciudadano_id', $this->ana->id)->where('centro_id', $this->ciamVallecas->id)->activas()->exists());
        $this->assertSame(1, InscripcionSesion::where('ciudadano_id', $this->ana->id)->vigentes()->count());
    }

    /**
     * TF-ACT-07 — La inscripción es sesión a sesión (F6-1): no se extiende a las demás.
     *
     * @return void
     */
    #[Test]
    public function tf_act_07_sesion_a_sesion(): void
    {
        $persona = $this->persona($this->tallerCiam);

        $this->servicio->inscribir($this->sesion9, $persona, $this->psicologaVallecas);

        $this->assertSame([$this->sesion9->id], InscripcionSesion::where('ciudadano_id', $persona->id)->pluck('sesion_actividad_id')->all());
        $this->assertFalse(method_exists($this->servicio, 'inscribirEnActividad'));
    }

    /**
     * TF-ACT-08 — No se inscribe en una sesión pasada, cancelada o celebrada. [negativo]
     *
     * @return void
     */
    #[Test]
    public function tf_act_08_sesion_pasada_cancelada_o_celebrada(): void
    {
        $persona = $this->persona($this->tallerCiam);
        $cancelada = $this->sesion($this->tallerCiam, '2026-11-30', ['estado' => 'cancelada']);
        $celebrada = $this->sesion($this->tallerCiam, '2026-11-02', ['estado' => 'celebrada', 'hora_inicio' => '08:00']);

        foreach ([$this->sesionPasada, $cancelada, $celebrada] as $sesion) {
            try {
                $this->servicio->inscribir($sesion, $persona, $this->psicologaVallecas);
                $this->fail("Se ha inscrito en la sesión del {$sesion->fecha->toDateString()} ({$sesion->estado}).");
            } catch (DomainException $e) {
                $this->assertSame('Solo se inscribe en sesiones programadas que aún no han empezado.', $e->getMessage());
            }
        }

        $this->assertSame(0, InscripcionSesion::where('ciudadano_id', $persona->id)->count());
    }

    /**
     * TF-ACT-09 — Sin duplicados; tras la baja, se puede volver a inscribir.
     *
     * @return void
     */
    #[Test]
    public function tf_act_09_sin_duplicados(): void
    {
        $persona = $this->persona($this->tallerCiam);
        $primera = $this->servicio->inscribir($this->sesion9, $persona, $this->psicologaVallecas);

        try {
            $this->servicio->inscribir($this->sesion9, $persona, $this->psicologaVallecas);
            $this->fail('Se ha inscrito dos veces.');
        } catch (DomainException $e) {
            $this->assertSame('La persona ya está inscrita en esta sesión.', $e->getMessage());
        }

        $this->servicio->darDeBaja($primera, 'No puede asistir', $this->psicologaVallecas);
        $this->servicio->inscribir($this->sesion9, $persona, $this->psicologaVallecas);

        $this->assertSame(2, InscripcionSesion::where('ciudadano_id', $persona->id)->count());
        $this->assertSame(1, InscripcionSesion::where('ciudadano_id', $persona->id)->vigentes()->count());
    }

    /**
     * TF-ACT-09 bis — La base de datos tampoco admite dos inscripciones vigentes. [negativo]
     *
     * @return void
     */
    #[Test]
    public function tf_act_09_bis_indice_unico_de_vigentes(): void
    {
        $persona = $this->persona($this->tallerCiam);
        $this->servicio->inscribir($this->sesion9, $persona, $this->psicologaVallecas);

        $this->expectException(QueryException::class);

        InscripcionSesion::create([
            'sesion_actividad_id' => $this->sesion9->id,
            'ciudadano_id' => $persona->id,
            'via' => ViaInscripcion::Libre,
            'estado' => EstadoInscripcionSesion::Inscrita,
            'inscrita_por_id' => $this->psicologaVallecas->id,
        ]);
    }

    /**
     * TF-ACT-10 — Una actividad solo de prescripción no admite inscripción libre (servidor).
     *
     * @return void
     */
    #[Test]
    public function tf_act_10_actividad_solo_de_prescripcion(): void
    {
        $this->tallerCiam->update(['modo_acceso' => 'prescripcion']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Esta actividad es solo de prescripción');

        $this->servicio->inscribir($this->sesion9->fresh(), $this->persona($this->tallerCiam), $this->psicologaVallecas);
    }

    /**
     * TF-ACT-11 — La baja exige motivo, libera la plaza y no borra la inscripción.
     *
     * @return void
     */
    #[Test]
    public function tf_act_11_baja_de_una_inscripcion(): void
    {
        [$inscripcion] = $this->llenarLibre($this->sesion9, 1);

        try {
            $this->servicio->darDeBaja($inscripcion, '   ', $this->psicologaVallecas);
            $this->fail('Se ha dado de baja sin motivo.');
        } catch (DomainException $e) {
            $this->assertSame('Indica el motivo de la baja.', $e->getMessage());
        }

        $this->assertSame(5, $this->sesion9->plazasLibres(ViaInscripcion::Libre));

        $this->servicio->darDeBaja($inscripcion, 'Cambio de horario laboral', $this->psicologaVallecas);

        $inscripcion->refresh();
        $this->assertSame(EstadoInscripcionSesion::Baja, $inscripcion->estado);
        $this->assertSame('Cambio de horario laboral', $inscripcion->motivo_baja);
        $this->assertSame($this->psicologaVallecas->id, $inscripcion->baja_por_id);
        $this->assertSame(6, $this->sesion9->plazasLibres(ViaInscripcion::Libre));

        // Nada se borra: la base de datos rechaza el DELETE
        $this->expectException(QueryException::class);
        DB::table('inscripciones_sesion')->where('id', $inscripcion->id)->delete();
    }

    /**
     * TF-ACT-12 — Inscribir en una actividad con prestación crea la de la persona, una por
     * actividad (F6-10); sin prestación vinculada, no.
     *
     * @return void
     */
    #[Test]
    public function tf_act_12_prestacion_de_la_persona(): void
    {
        $persona = $this->persona($this->tallerCiam);

        $this->servicio->inscribir($this->sesion9, $persona, $this->psicologaVallecas);

        $prestaciones = PrestacionPersona::where('ciudadano_id', $persona->id)->get();
        $this->assertCount(1, $prestaciones);
        $this->assertSame($this->pTaller->id, $prestaciones[0]->prestacion_id);
        $this->assertSame(EstadoPrestacionPersona::Activa, $prestaciones[0]->estado);

        $this->servicio->inscribir($this->sesion16, $persona, $this->psicologaVallecas);
        $this->assertSame(1, PrestacionPersona::where('ciudadano_id', $persona->id)->count());

        $enCharla = $this->persona();
        $this->servicio->inscribir($this->sesionCharla, $enCharla, $this->directoraNorte);
        $this->assertSame(0, PrestacionPersona::where('ciudadano_id', $enCharla->id)->count());
    }

    /**
     * Una actividad dada de baja no admite inscripciones.
     *
     * @return void
     */
    #[Test]
    public function actividad_de_baja_no_admite_inscripciones(): void
    {
        Actividad::whereKey($this->tallerCiam->id)->update(['activa' => false, 'fecha_baja' => '2026-11-01']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('La actividad está dada de baja');

        $this->servicio->inscribir($this->sesion9->fresh(), $this->persona($this->tallerCiam), $this->psicologaVallecas);
    }

    /**
     * Una persona protegida que el actor no puede ver no se inscribe (RN-17). [negativo]
     *
     * @return void
     */
    #[Test]
    public function persona_protegida_no_visible_no_se_inscribe(): void
    {
        $this->expectException(AuthorizationException::class);

        // `$marta` está protegida y su historia es del CSS Norte, no del CIAM
        $this->servicio->inscribir($this->sesion9, $this->marta, $this->psicologaVallecas, inscribirEnCentro: true);
    }
}
