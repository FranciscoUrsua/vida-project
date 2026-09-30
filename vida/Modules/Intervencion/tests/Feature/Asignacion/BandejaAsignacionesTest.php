<?php

namespace Modules\Intervencion\Tests\Feature\Asignacion;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Centro\Enums\EstadoAsignacionPendiente;
use Modules\Centro\Enums\ModoAsignacionCentro;
use Modules\Centro\Enums\MotivoAsignacionPendiente;
use Modules\Centro\Enums\TipoAsignacionPendiente;
use Modules\Centro\Models\AsignacionCentro;
use Modules\Centro\Models\AsignacionPendiente;
use Modules\Centro\Models\Centro;
use Modules\Centro\Tests\Concerns\AsignacionTestSetup;
use Modules\Intervencion\Enums\EstadoRepartoCasos;
use Modules\Intervencion\Enums\OrigenAsignacionReferencia;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Intervencion\Models\RepartoCasos;
use Modules\Intervencion\Services\Asignacion\RepartoCasosService;
use Modules\Supervision\Http\Livewire\AsignacionesPage;
use Modules\Supervision\Http\Livewire\RepartoCasosPage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo H: bandeja de asignaciones (TF-ASG-34) y pantallas
 * del supervisor (bandeja, reparto, actividad) y bloque de la ficha.
 *
 * @see docs/instrucciones-cli/2026-09-asignacion-tests.md
 */
class BandejaAsignacionesTest extends TestCase
{
    use AsignacionTestSetup;
    use RefreshDatabase;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->montarEscenarioAsignacion();
        $this->fijarSemilla(7);
    }

    /**
     * Persona sin centro en la bandeja del centro dado.
     *
     * @param string $nombre
     * @param Centro $centro
     * @return AsignacionPendiente
     */
    private function sinCentroEn(string $nombre, Centro $centro): AsignacionPendiente
    {
        return AsignacionPendiente::create([
            'ciudadano_id' => $this->crearCiudadano($nombre)->id,
            'tipo' => TipoAsignacionPendiente::SinCentro,
            'tipo_centro' => $this->tipoCss,
            'motivo' => MotivoAsignacionPendiente::SinCobertura,
            'centro_id' => $centro->id,
            'estado' => EstadoAsignacionPendiente::Pendiente,
        ]);
    }

    /**
     * Referencia vigente de ts1 en CSS Norte para una historia nueva.
     *
     * @param Ciudadano $ciudadano
     * @return HistoriaSocial
     */
    private function casoDeTs1(Ciudadano $ciudadano): HistoriaSocial
    {
        $historia = $this->historiaDe($ciudadano);

        AsignacionProfesional::create([
            'historia_id' => $historia->id,
            'profesional_id' => $this->ts1->id,
            'centro_id' => $this->cssNorte->id,
            'origen' => OrigenAsignacionReferencia::Sorteo,
            'cuenta_en_reparto' => true,
            'fecha_inicio' => '2026-01-15',
        ]);

        return $historia;
    }

    /**
     * TF-ASG-34 — Cada supervisor ve solo las pendientes de su centro; un
     * profesional sin rol de supervisión no entra.
     *
     * @return void
     */
    #[Test]
    public function cada_supervisor_ve_solo_la_bandeja_de_su_centro(): void
    {
        $this->sinCentroEn('Norte Pendiente', $this->cssNorte);
        $this->sinCentroEn('Sur Pendiente', $this->cssSur);

        $this->actingAs($this->supervisor)
            ->get(route('supervision.asignaciones'))
            ->assertOk()
            ->assertSee('Norte Pendiente')
            ->assertDontSee('Sur Pendiente');

        $this->actingAs($this->ts1)
            ->get(route('supervision.asignaciones'))
            ->assertForbidden();

        $this->actingAs($this->supervisorSur)
            ->get(route('supervision.asignaciones'))
            ->assertOk()
            ->assertSee('Sur Pendiente')
            ->assertDontSee('Norte Pendiente');
    }

    /**
     * Un supervisor no puede resolver una entrada de otro centro aunque conozca su id. [negativo]
     *
     * @return void
     */
    #[Test]
    public function no_se_resuelve_una_entrada_de_otro_centro(): void
    {
        $delNorte = $this->sinCentroEn('Norte Pendiente', $this->cssNorte);

        $this->actingAs($this->supervisorSur);
        Livewire::test(AsignacionesPage::class)
            ->call('iniciar', $delNorte->id, 'asignar')
            ->set('centroElegidoId', $this->cssSur->id)
            ->set('motivo', 'Intento desde otro centro')
            ->call('resolver')
            ->assertHasErrors('motivo');

        $this->assertSame(EstadoAsignacionPendiente::Pendiente, $delNorte->fresh()->estado);
        $this->assertSame(0, AsignacionCentro::count());
    }

    /**
     * Asignar centro a mano desde la bandeja exige motivo y crea la asignación manual.
     *
     * @return void
     */
    #[Test]
    public function asignar_centro_desde_la_bandeja_exige_motivo(): void
    {
        $pendiente = $this->sinCentroEn('Sin Centro', $this->cssNorte);

        $this->actingAs($this->supervisor);
        $pagina = Livewire::test(AsignacionesPage::class)
            ->call('iniciar', $pendiente->id, 'asignar')
            ->set('centroElegidoId', $this->cssNorte->id)
            ->call('resolver')
            ->assertHasErrors('motivo');

        $this->assertSame(0, AsignacionCentro::count());

        $pagina->set('motivo', 'Vive en el límite del barrio')
            ->call('resolver')
            ->assertHasNoErrors()
            ->assertSet('resolviendoId', null);

        $asignacion = AsignacionCentro::sole();
        $this->assertSame($this->cssNorte->id, $asignacion->centro_id);
        $this->assertSame(ModoAsignacionCentro::Manual, $asignacion->modo);
        $this->assertSame($this->supervisor->id, $asignacion->asignado_por_id);
        $this->assertSame(EstadoAsignacionPendiente::Resuelta, $pendiente->fresh()->estado);
    }

    /**
     * Una historia sin referencia se asigna a un profesional del centro, aunque
     * el sorteo no tenga a nadie; no se ofrece a quien no puede ser referencia.
     *
     * @return void
     */
    #[Test]
    public function asignar_referencia_desde_la_bandeja(): void
    {
        $historia = $this->historiaDe($this->ana);
        $pendiente = AsignacionPendiente::create([
            'ciudadano_id' => $this->ana->id,
            'historia_id' => $historia->id,
            'tipo' => TipoAsignacionPendiente::SinReferencia,
            'motivo' => MotivoAsignacionPendiente::SinElegibles,
            'centro_id' => $this->cssNorte->id,
            'estado' => EstadoAsignacionPendiente::Pendiente,
        ]);

        $this->actingAs($this->supervisor);
        $pagina = Livewire::test(AsignacionesPage::class)->call('iniciar', $pendiente->id, 'asignar');

        $pagina->set('profesionalElegidoId', $this->educador->id)
            ->set('motivo', 'Prueba')
            ->call('resolver')
            ->assertHasErrors('motivo');
        $this->assertSame(0, AsignacionProfesional::count());

        $pagina->set('profesionalElegidoId', $this->ts3->id)
            ->call('resolver')
            ->assertHasNoErrors();

        $asignacion = AsignacionProfesional::sole();
        $this->assertSame($this->ts3->id, $asignacion->profesional_id);
        $this->assertSame(OrigenAsignacionReferencia::Manual, $asignacion->origen);
        $this->assertFalse($asignacion->cuenta_en_reparto);
        $this->assertSame($this->cssNorte->id, $asignacion->centro_id);
        $this->assertSame(EstadoAsignacionPendiente::Resuelta, $pendiente->fresh()->estado);
    }

    /**
     * La propuesta de cambio de domicilio se confirma o se descarta desde la bandeja.
     *
     * @return void
     */
    #[Test]
    public function confirmar_y_descartar_cambios_de_domicilio(): void
    {
        $propuestas = collect(['Ana Traslado', 'Luis Se Queda'])->map(function (string $nombre) {
            $ciudadano = $this->crearCiudadano($nombre);
            AsignacionCentro::create([
                'ciudadano_id' => $ciudadano->id,
                'tipo_centro' => $this->tipoCss,
                'centro_id' => $this->cssNorte->id,
                'modo' => ModoAsignacionCentro::Geografico,
                'fecha_inicio' => '2026-01-01',
            ]);

            return AsignacionPendiente::create([
                'ciudadano_id' => $ciudadano->id,
                'tipo' => TipoAsignacionPendiente::CambioDomicilio,
                'tipo_centro' => $this->tipoCss,
                'centro_id' => $this->cssNorte->id,
                'centro_propuesto_id' => $this->cssSur->id,
                'estado' => EstadoAsignacionPendiente::Pendiente,
            ]);
        });

        $this->actingAs($this->supervisor);
        Livewire::test(AsignacionesPage::class)
            ->assertSee('Trasladar a CSS Sur')
            ->call('iniciar', $propuestas[0]->id, 'confirmar')
            ->set('motivo', 'Traslado confirmado')
            ->call('resolver')
            ->assertHasNoErrors()
            ->call('iniciar', $propuestas[1]->id, 'descartar')
            ->set('motivo', 'Cambio temporal de domicilio')
            ->call('resolver')
            ->assertHasNoErrors();

        $this->assertSame($this->cssSur->id, AsignacionCentro::vigentes()->where('ciudadano_id', $propuestas[0]->ciudadano_id)->value('centro_id'));
        $this->assertSame($this->cssNorte->id, AsignacionCentro::vigentes()->where('ciudadano_id', $propuestas[1]->ciudadano_id)->value('centro_id'));
        $this->assertSame(EstadoAsignacionPendiente::Descartada, $propuestas[1]->fresh()->estado);
    }

    /**
     * Desde la actividad del equipo se prepara el reparto, que se revisa,
     * se modifica y se confirma en su pantalla.
     *
     * @return void
     */
    #[Test]
    public function reparto_desde_la_actividad_del_equipo_hasta_la_confirmacion(): void
    {
        $historias = collect(range(1, 3))->map(fn (int $i) => $this->casoDeTs1($this->crearCiudadano("Caso {$i}")));

        $this->actingAs($this->supervisor)
            ->get(route('supervision.asignaciones', 'actividad'))
            ->assertOk()
            ->assertSee('Repartir sus casos');

        Livewire::test(AsignacionesPage::class, ['pestana' => 'actividad'])
            ->call('iniciarReparto', $this->ts1->id)
            ->call('proponerReparto')
            ->assertHasErrors('motivoReparto')
            ->set('motivoReparto', 'Traslado a otro centro')
            ->call('proponerReparto')
            ->assertRedirect(route('supervision.asignaciones.reparto', RepartoCasos::sole()));

        $reparto = RepartoCasos::sole();
        $this->assertSame(3, AsignacionProfesional::vigente()->where('profesional_id', $this->ts1->id)->count());

        $linea = $reparto->lineas()->where('historia_id', $historias[0]->id)->sole();
        $otro = $linea->profesional_destino_id === $this->ts2->id ? $this->ts3 : $this->ts2;

        Livewire::test(RepartoCasosPage::class, ['reparto' => $reparto])
            ->assertSee('Caso 1')
            ->call('cambiarDestino', $linea->id, $this->ts1->id)
            ->assertHasErrors('reparto')
            ->call('cambiarDestino', $linea->id, $otro->id)
            ->call('confirmar')
            ->assertHasNoErrors()
            ->assertSee('Reparto confirmado');

        $this->assertSame(EstadoRepartoCasos::Confirmado, $reparto->fresh()->estado);
        $this->assertSame($otro->id, AsignacionProfesional::vigente()->where('historia_id', $historias[0]->id)->value('profesional_id'));
        $this->assertSame(0, AsignacionProfesional::vigente()->where('profesional_id', $this->ts1->id)->count());
    }

    /**
     * El reparto de un centro no lo abre la supervisión de otro. [negativo]
     *
     * @return void
     */
    #[Test]
    public function el_reparto_de_otro_centro_no_se_abre(): void
    {
        $this->casoDeTs1($this->crearCiudadano('Caso'));
        $reparto = app(RepartoCasosService::class)->proponer($this->cssNorte, $this->ts1, 'Salida', $this->supervisor);

        $this->actingAs($this->supervisorSur)
            ->get(route('supervision.asignaciones.reparto', $reparto))
            ->assertNotFound();

        $this->actingAs($this->supervisor)
            ->get(route('supervision.asignaciones.reparto', $reparto))
            ->assertOk()
            ->assertSee('Reparto de los casos de');
    }

    /**
     * La ficha muestra el centro por tipo y la referencia con su modo.
     *
     * @return void
     */
    #[Test]
    public function la_ficha_muestra_centro_y_referencia(): void
    {
        AsignacionCentro::create([
            'ciudadano_id' => $this->ana->id,
            'tipo_centro' => $this->tipoCss,
            'centro_id' => $this->cssNorte->id,
            'modo' => ModoAsignacionCentro::Geografico,
            'fecha_inicio' => '2026-01-01',
        ]);
        $historia = $this->historiaDe($this->ana);
        AsignacionProfesional::create([
            'historia_id' => $historia->id,
            'profesional_id' => $this->ts2->id,
            'centro_id' => $this->cssNorte->id,
            'origen' => OrigenAsignacionReferencia::Sorteo,
            'cuenta_en_reparto' => true,
            'fecha_inicio' => '2026-01-15',
        ]);

        $this->actingAs($this->ts1)
            ->get(route('ciudadania.ciudadano.ficha', $this->ana->id))
            ->assertOk()
            ->assertSee('Centro y referencia')
            ->assertSee('CSS Norte')
            ->assertSee('Por domicilio')
            ->assertSee('Ts2 Prueba')
            ->assertSee('Por sorteo');
    }
}
