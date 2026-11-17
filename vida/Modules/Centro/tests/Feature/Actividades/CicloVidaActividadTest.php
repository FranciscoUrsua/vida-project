<?php

namespace Modules\Centro\Tests\Feature\Actividades;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Centro\Enums\EstadoInscripcionSesion;
use Modules\Centro\Models\Actividad;
use Modules\Centro\Models\InscripcionSesion;
use Modules\Centro\Models\Prescripcion;
use Modules\Centro\Models\SesionActividad;
use Modules\Centro\Services\Actividades\CicloVidaActividadService;
use Modules\Centro\Services\Actividades\InscripcionActividadService;
use Modules\Centro\Tests\Concerns\ActividadesTestSetup;
use Modules\Mensajes\Models\Alerta;
use Modules\Supervision\Http\Livewire\ActividadesPage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo D — Ciclo de vida de actividades y sesiones (TF-ACT-17 a 21; fase 6, F6-5 y F6-6).
 *
 * Nada con personas se borra: la actividad se da de baja y la sesión con inscripciones o
 * prescripciones se cancela, con aviso a quien inscribió o prescribió.
 */
class CicloVidaActividadTest extends TestCase
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
     * TF-ACT-17 — La directora da de baja el taller: sigue existiendo, sale del listado por
     * defecto (sí con el filtro) y no admite inscripciones.
     *
     * @return void
     */
    #[Test]
    public function tf_act_17_baja_de_actividad(): void
    {
        $componente = Livewire::actingAs($this->directoraVallecas)->test(ActividadesPage::class)
            ->assertSee('Taller de autonomía')
            ->call('abrirBaja', $this->tallerCiam->id)
            ->call('confirmarBaja')
            ->assertHasErrors('motivoBaja')
            ->set('motivoBaja', 'Fin de la financiación')
            ->call('confirmarBaja')
            ->assertHasNoErrors();

        $taller = Actividad::find($this->tallerCiam->id);
        $this->assertNotNull($taller);
        $this->assertFalse($taller->activa);
        $this->assertEquals(today(), $taller->fecha_baja);
        $this->assertSame('Fin de la financiación', $taller->motivo_baja);

        $componente->assertDontSee('Taller de autonomía')
            ->set('verBajas', true)
            ->assertSee('Taller de autonomía');

        // Sus sesiones futuras quedan canceladas; la pasada, no
        $this->assertSame('cancelada', $this->sesion9->fresh()->estado);
        $this->assertSame('programada', $this->sesionPasada->fresh()->estado);

        $this->expectException(\DomainException::class);
        app(InscripcionActividadService::class)->inscribir($this->sesion9->fresh(), $this->persona($this->tallerCiam), $this->psicologaVallecas);
    }

    /**
     * TF-ACT-18 — La sesión con inscritos no se elimina; se cancela con motivo, sus
     * inscripciones se dan de baja con «Sesión cancelada» y se avisa a quien inscribió
     * cada una. [negativo]
     *
     * @return void
     */
    #[Test]
    public function tf_act_18_cancelar_sesion_con_inscritos(): void
    {
        $this->llenarLibre($this->sesion9, 2);
        $this->prescribir($this->sesion9, $this->ana);

        $componente = Livewire::actingAs($this->directoraVallecas)->test(ActividadesPage::class)
            ->call('abrirSesiones', $this->tallerCiam->id)
            ->call('eliminarSesion', $this->sesion9->id)
            ->assertHasErrors('sesiones');

        $this->assertNotNull(SesionActividad::find($this->sesion9->id));

        $componente->call('abrirCancelacion', $this->sesion9->id)
            ->set('motivoCancelacion', 'Baja de la monitora')
            ->call('confirmarCancelacion')
            ->assertHasNoErrors();

        $sesion = $this->sesion9->fresh();
        $this->assertSame('cancelada', $sesion->estado);
        $this->assertSame('Baja de la monitora', $sesion->motivo_cancelacion);
        $this->assertSame($this->directoraVallecas->id, $sesion->cancelada_por_id);

        $inscripciones = InscripcionSesion::where('sesion_actividad_id', $sesion->id)->get();
        $this->assertCount(3, $inscripciones);
        $this->assertTrue($inscripciones->every(fn ($i) => $i->estado === EstadoInscripcionSesion::Baja && $i->motivo_baja === CicloVidaActividadService::MOTIVO_SESION_CANCELADA));

        // Un aviso por quien inscribió: la psicóloga (2 personas) y el TS que prescribió (1)
        $avisos = Alerta::where('origen_type', SesionActividad::class)->where('origen_id', $sesion->id)->get();
        $this->assertEqualsCanonicalizing([$this->psicologaVallecas->id, $this->tsNorte->id], $avisos->pluck('destinatario_usuario_id')->all());
        $this->assertStringContainsString('2 personas', $avisos->firstWhere('destinatario_usuario_id', $this->psicologaVallecas->id)->cuerpo);
        $this->assertStringNotContainsString($this->ana->nombre, $avisos->firstWhere('destinatario_usuario_id', $this->tsNorte->id)->cuerpo);

        // La prescripción también se cancela
        $this->assertSame('cancelada', Prescripcion::where('ciudadano_id', $this->ana->id)->value('estado'));
    }

    /**
     * TF-ACT-19 — Una sesión sin inscripciones ni prescripciones se puede eliminar.
     *
     * @return void
     */
    #[Test]
    public function tf_act_19_eliminar_sesion_vacia(): void
    {
        Livewire::actingAs($this->directoraVallecas)->test(ActividadesPage::class)
            ->call('abrirSesiones', $this->tallerCiam->id)
            ->call('eliminarSesion', $this->sesion23->id)
            ->assertHasNoErrors();

        $this->assertNull(SesionActividad::find($this->sesion23->id));
    }

    /**
     * TF-ACT-20 — Una sesión pasada se marca como celebrada, sin registrar asistencia; una
     * futura, no.
     *
     * @return void
     */
    #[Test]
    public function tf_act_20_marcar_celebrada(): void
    {
        Livewire::actingAs($this->directoraVallecas)->test(ActividadesPage::class)
            ->call('abrirSesiones', $this->tallerCiam->id)
            ->call('marcarCelebrada', $this->sesionPasada->id)
            ->assertHasNoErrors()
            ->call('marcarCelebrada', $this->sesion9->id)
            ->assertHasErrors('sesiones');

        $this->assertSame('celebrada', $this->sesionPasada->fresh()->estado);
        $this->assertSame('programada', $this->sesion9->fresh()->estado);
    }

    /**
     * TF-ACT-21 — La dirección ve los inscritos de la sesión con vía, quién inscribió y
     * cuándo, y el aforo ocupado por vía.
     *
     * @return void
     */
    #[Test]
    public function tf_act_21_vista_de_inscritos(): void
    {
        [$libre] = $this->llenarLibre($this->sesion9, 1);
        $this->prescribir($this->sesion9, $this->ana);

        $componente = Livewire::actingAs($this->directoraVallecas)->test(ActividadesPage::class)
            ->call('abrirSesiones', $this->tallerCiam->id)
            ->call('verInscritos', $this->sesion9->id);

        $filas = $componente->get('inscritos');
        $this->assertCount(2, $filas);
        $this->assertSame($libre->ciudadano->nombre_completo, $filas[0]['persona']['nombre']);
        $this->assertSame('Acceso libre', $filas[0]['via']->label());
        $this->assertSame($this->psicologaVallecas->nombreVisible(), $filas[0]['inscrita_por']);
        $this->assertSame('02/11/2026 10:00', $filas[0]['fecha']);
        $this->assertSame('Prescripción', $filas[1]['via']->label());

        $componente->assertSee('Acceso libre:')->assertSee('1 / 6')->assertSee('Prescripción:')->assertSee('1 / 4');
    }

    /**
     * El estado de una sesión no se cambia desde el formulario: «cancelada» a mano dejaría
     * las inscripciones vigentes. [negativo]
     *
     * @return void
     */
    #[Test]
    public function el_formulario_no_cambia_el_estado(): void
    {
        $this->llenarLibre($this->sesion9, 1);

        $this->assertFalse(property_exists(ActividadesPage::class, 'sesionEstado'));

        Livewire::actingAs($this->directoraVallecas)->test(ActividadesPage::class)
            ->call('abrirSesiones', $this->tallerCiam->id)
            ->call('editarSesion', $this->sesion9->id)
            ->set('sesionNotas', 'Traer ropa cómoda')
            ->call('guardarSesion')
            ->assertHasNoErrors();

        $this->assertSame('programada', $this->sesion9->fresh()->estado);
        $this->assertSame('Traer ropa cómoda', $this->sesion9->fresh()->notas);
    }

    /**
     * El aforo de una sesión no baja de las plazas ya ocupadas.
     *
     * @return void
     */
    #[Test]
    public function el_aforo_no_baja_de_lo_ocupado(): void
    {
        $this->llenarLibre($this->sesion9, 3);

        Livewire::actingAs($this->directoraVallecas)->test(ActividadesPage::class)
            ->call('abrirSesiones', $this->tallerCiam->id)
            ->call('editarSesion', $this->sesion9->id)
            ->set('sesionAforoTotal', 6)
            ->call('guardarSesion')
            ->assertHasErrors('sesionAforoTotal');

        $this->assertNull($this->sesion9->fresh()->aforo_total);
    }

    /**
     * Fuera de la supervisión del centro no se gestionan sus actividades. [negativo]
     *
     * @return void
     */
    #[Test]
    public function solo_la_supervision_del_centro_gestiona(): void
    {
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        app(CicloVidaActividadService::class)->cancelarSesion($this->sesion9, 'Prueba', $this->psicologaVallecas);
    }
}
