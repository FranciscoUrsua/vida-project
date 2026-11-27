<?php

namespace Modules\Intervencion\Tests\Feature\Usabilidad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Centro\Models\ColeccionPlazas;
use Modules\Centro\Models\Espacio;
use Modules\Centro\Models\InscripcionSesion;
use Modules\Centro\Models\ListaEspera;
use Modules\Centro\Models\Plaza;
use Modules\Centro\Models\Prescripcion;
use Modules\Centro\Models\TipoEspacio;
use Modules\Centro\Services\Actividades\InscripcionActividadService;
use Modules\Centro\Tests\Concerns\ActividadesTestSetup;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Prescripciones en la Historia Social (U2, U2.1, U2.2; TF-USA-05 a 09): la banda muestra el
 * nombre del recurso, la modal da el resumen y la cancelación exige motivo, policy y RN-15.
 *
 * Datos: Ana (CSS Norte, TS de referencia `tsNorte`) con una prescripción al «Taller de
 * autonomía» del CIAM Puente de Vallecas hecha desde su PISO; `tecnicaArea` (intervención sin
 * centro, modo lectura).
 *
 * @see docs/instrucciones-cli/2026-11-usabilidad-historia-social.md, paso 2
 */
class PrescripcionesHistoriaTest extends TestCase
{
    use ActividadesTestSetup;
    use RefreshDatabase;

    private Prescripcion $prescripcion;

    /**
     * Actividades del circuito y una prescripción de Ana al taller del CIAM.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarActividades();

        $this->actingAs($this->tsNorte);
        $this->prescripcion = $this->prescribir($this->sesion23, $this->ana);
        $this->prescripcion->update(['plan_intervencion_id' => $this->pisoAna->id, 'notas' => 'Acude con su hija.']);
    }

    /**
     * Historia Social de Ana con la modal de la prescripción abierta.
     *
     * @return Testable
     */
    private function modalAbierta()
    {
        return Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna])
            ->call('verPrescripcion', $this->prescripcion->id);
    }

    /**
     * TF-USA-05 — La banda muestra el nombre de la actividad y el estado.
     *
     * @return void
     */
    #[Test]
    public function la_banda_muestra_el_nombre_del_recurso_y_su_estado(): void
    {
        Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna])
            ->assertSee('Taller de autonomía · '.$this->prescripcion->fresh()->etiquetaEstado())
            ->assertDontSee('· Actividad');
    }

    /**
     * TF-USA-06 — La modal de resumen muestra prestación, destino, estado, fechas, plan y autor.
     *
     * @return void
     */
    #[Test]
    public function la_modal_muestra_el_resumen_de_la_prescripcion(): void
    {
        $this->modalAbierta()
            ->assertSee('Prestación')
            ->assertSee('Taller de autonomía')
            ->assertSee('CIAM Puente de Vallecas')
            ->assertSee('Sesión del 23/11/2026')
            ->assertSee($this->prescripcion->fresh()->etiquetaEstado())
            ->assertSee(today()->format('d/m/Y'))
            ->assertSee('PISO')
            ->assertSee($this->tsNorte->profesional->nombre_completo)
            ->assertSee('Acude con su hija.')
            ->assertSee('Cancelar prescripción');
    }

    /**
     * TF-USA-07 [negativo] — Sin motivo o con menos de 10 caracteres no se cancela.
     *
     * @return void
     */
    #[Test]
    public function el_motivo_es_obligatorio_y_de_al_menos_diez_caracteres(): void
    {
        $estado = $this->prescripcion->fresh()->estado;

        $this->modalAbierta()
            ->call('iniciarCancelacionPrescripcion')
            ->set('motivoCancelacion', '')
            ->call('cancelarPrescripcion')
            ->assertHasErrors(['motivoCancelacion' => 'required'])
            ->set('motivoCancelacion', 'Corto')
            ->call('cancelarPrescripcion')
            ->assertHasErrors(['motivoCancelacion' => 'min']);

        $this->assertSame($estado, $this->prescripcion->fresh()->estado);
    }

    /**
     * TF-USA-08 — Con motivo: cancelada, motivo guardado, plaza libre y lista de espera
     * cancelada; si falla un paso, no cambia nada.
     *
     * @return void
     */
    #[Test]
    public function la_cancelacion_libera_la_plaza_y_la_lista_de_espera_en_una_transaccion(): void
    {
        // Prescripción a plazas con plaza asignada y una entrada (en curso) en la lista de espera
        $coleccion = ColeccionPlazas::create(['centro_id' => $this->cssNorte->id, 'nombre' => 'Pisos tutelados', 'tipo_plaza' => 'alojamiento_dia', 'modo_acceso' => 'prescripcion_directa', 'capacidad' => 2, 'activa' => true, 'fecha_alta' => '2026-10-01']);
        $tipoEspacio = TipoEspacio::firstOrCreate(['slug' => 'habitacion-usa'], ['nombre' => 'Habitación', 'activo' => true]);
        $espacio = Espacio::create(['coleccion_plazas_id' => $coleccion->id, 'tipo_espacio_id' => $tipoEspacio->id, 'nombre' => 'Hab 1', 'capacidad' => 1, 'accesible' => false, 'activo' => true]);
        $plaza = Plaza::create(['espacio_id' => $espacio->id, 'nombre' => 'Plaza 1', 'estado' => 'ocupada', 'activa' => true]);
        $presc = Prescripcion::create([
            'profesional_id' => $this->tsNorte->profesional_id, 'ciudadano_id' => $this->ana->id,
            'tipo_destino' => 'coleccion_plazas', 'destino_id' => $coleccion->id, 'plaza_id' => $plaza->id,
            'estado' => 'asignada', 'fecha_prescripcion' => '2026-10-15',
        ]);
        $espera = ListaEspera::create(['prescripcion_id' => $presc->id, 'coleccion_plazas_id' => $coleccion->id, 'profesional_alerta_id' => $this->tsNorte->profesional_id, 'estado' => 'activa', 'posicion' => 1, 'fecha_entrada' => now()]);

        Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna])
            ->call('verPrescripcion', $presc->id)
            ->assertSee('Pisos tutelados')
            ->call('iniciarCancelacionPrescripcion')
            ->set('motivoCancelacion', 'La persona ha encontrado alojamiento propio.')
            ->call('cancelarPrescripcion')
            ->assertHasNoErrors()
            ->assertSet('prescripcionAbiertaId', null);

        $presc->refresh();
        $this->assertSame('cancelada', $presc->estado);
        $this->assertSame('La persona ha encontrado alojamiento propio.', $presc->motivo_cancelacion);
        $this->assertNull($presc->plaza_id);
        $this->assertSame('libre', $plaza->fresh()->estado);
        $this->assertSame('cancelada', $espera->fresh()->estado);

        // Si falla la baja de la inscripción, la prescripción de la sesión sigue como estaba
        $this->assertNotNull(InscripcionSesion::where('prescripcion_id', $this->prescripcion->id)->vigentes()->first());
        $estado = $this->prescripcion->fresh()->estado;
        $this->mock(InscripcionActividadService::class)
            ->shouldReceive('registrarBaja')->andThrow(new RuntimeException('Fallo simulado'));

        try {
            $this->modalAbierta()
                ->call('iniciarCancelacionPrescripcion')
                ->set('motivoCancelacion', 'Ya no puede asistir los lunes.')
                ->call('cancelarPrescripcion');
            $this->fail('La baja simulada tenía que fallar.');
        } catch (RuntimeException $e) {
            $this->assertSame('Fallo simulado', $e->getMessage());
        }

        $this->assertSame($estado, $this->prescripcion->fresh()->estado);
        $this->assertNull($this->prescripcion->fresh()->motivo_cancelacion);
    }

    /**
     * TF-USA-09 [negativo] — Sin centro (modo lectura) no se cancela: la modal se abre sin el
     * botón y la llamada directa se rechaza.
     *
     * @return void
     */
    #[Test]
    public function sin_centro_la_modal_no_ofrece_cancelar_y_la_accion_se_rechaza(): void
    {
        $estado = $this->prescripcion->fresh()->estado;
        $this->actingAs($this->tecnicaArea);

        $componente = $this->modalAbierta()
            ->assertSee('Taller de autonomía')
            ->assertDontSee('Cancelar prescripción');

        $componente->call('iniciarCancelacionPrescripcion')->assertForbidden();

        // Tras un 403 el componente no sigue: la llamada directa, con uno nuevo
        $this->modalAbierta()->set('cancelandoPrescripcion', true)
            ->set('motivoCancelacion', 'Intento sin poder actuar.')
            ->call('cancelarPrescripcion')
            ->assertForbidden();

        $this->assertSame($estado, $this->prescripcion->fresh()->estado);
    }
}
