<?php

namespace Modules\Centro\Tests\Feature\Asignacion;

use App\Enums\OrigenDireccion;
use App\Models\Ciudadano;
use App\Services\Geocodificacion\Callejero\ImportadorCallejero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centro\Enums\EstadoAsignacionPendiente;
use Modules\Centro\Enums\ModoAsignacionCentro;
use Modules\Centro\Enums\TipoAsignacionPendiente;
use Modules\Centro\Models\AsignacionPendiente;
use Modules\Centro\Services\Asignacion\AsignacionCentroService;
use Modules\Centro\Tests\Concerns\AsignacionTestSetup;
use Modules\Organizacion\Models\Configuracion;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FicheroCallejeroDePrueba as Fichero;
use Tests\TestCase;

/**
 * Asignación de centro con la sección censal del callejero oficial (TF-CAL-12).
 *
 * De punta a punta: el profesional escribe el domicilio, el observer lo normaliza con
 * el adaptador `callejero` y la asignación usa la sección oficial. Una persona con
 * asignación vigente no se reasigna al cambiar de domicilio: la propuesta va a la
 * bandeja del supervisor (RN-10).
 *
 * @see docs/instrucciones-cli/2026-11-callejero-madrid.md
 */
class AsignacionPorCallejeroTest extends TestCase
{
    use AsignacionTestSetup;
    use RefreshDatabase;

    private AsignacionCentroService $servicio;

    /**
     * Escenario de asignación (CSS Norte con el barrio 011, CSS Sur con el 012) y un
     * callejero con la calle Mayor 15 en la sección 2807901001 (barrio 011) y la 17 en
     * la 2807901003 (barrio 012). El adaptador activo es el del callejero.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEscenarioAsignacion();
        $this->servicio = app(AsignacionCentroService::class);

        $ruta = Fichero::escribir([
            Fichero::fila(['Codigo de barrio' => '01', 'Seccion censal' => '001']),
            Fichero::fila([
                'Codigo de numero' => '20104714', 'Literal de numeracion' => 'NUM000017', 'Direccion completa para el numero' => 'MAYOR, 000017',
                'Codigo de barrio' => '02', 'Seccion censal' => '003',
            ]),
        ], $this->carpetaTemporalDeTest('callejero').'/callejero.csv');
        app(ImportadorCallejero::class)->importar($ruta, 'local', $ruta);

        Configuracion::create(['clave' => 'geocoder.proveedor', 'valor' => 'callejero', 'tipo' => 'texto']);
    }

    /**
     * Persona con el domicilio escrito por el profesional.
     *
     * @param string $direccion
     *
     * @return Ciudadano
     */
    private function personaEn(string $direccion): Ciudadano
    {
        return Ciudadano::factory()->create([
            'direccion_texto' => $direccion,
            'origen_direccion' => OrigenDireccion::Profesional,
        ]);
    }

    #[Test]
    public function tf_cal_12_una_persona_sin_centro_queda_asignada_por_la_seccion_del_callejero(): void
    {
        $persona = $this->personaEn('Calle Mayor 15, 1º A');

        $this->assertSame('2807901001', $persona->fresh()->seccion_censal_codigo);

        $vigente = $this->servicio->vigente($persona, $this->tipoCss);
        $this->assertNotNull($vigente);
        $this->assertSame($this->cssNorte->id, $vigente->centro_id);
        $this->assertSame(ModoAsignacionCentro::Geografico, $vigente->modo);
        $this->assertSame('2807901001', $vigente->seccion_censal_codigo);
    }

    #[Test]
    public function tf_cal_12_bis_con_asignacion_vigente_un_domicilio_de_otro_centro_no_reasigna_y_avisa_al_supervisor(): void
    {
        $persona = $this->personaEn('Calle Mayor 15');
        $this->assertSame($this->cssNorte->id, $this->servicio->vigente($persona, $this->tipoCss)?->centro_id);

        $persona->update(['direccion_texto' => 'Calle Mayor 17']);

        $this->assertSame('2807901003', $persona->fresh()->seccion_censal_codigo);
        // Sigue en su centro
        $this->assertSame($this->cssNorte->id, $this->servicio->vigente($persona, $this->tipoCss)?->centro_id);

        // Y la propuesta de traslado espera en la bandeja del supervisor
        $propuesta = AsignacionPendiente::abiertas()
            ->where('ciudadano_id', $persona->id)
            ->where('tipo', TipoAsignacionPendiente::CambioDomicilio)
            ->sole();
        $this->assertSame($this->cssSur->id, $propuesta->centro_propuesto_id);
        $this->assertSame($this->cssNorte->id, $propuesta->centro_id);
        $this->assertSame(EstadoAsignacionPendiente::Pendiente, $propuesta->estado);
    }

    #[Test]
    public function tf_cal_12_ter_una_direccion_que_no_casa_no_asigna_centro(): void
    {
        $persona = $this->personaEn('Calle Mayor 99');

        $this->assertFalse($persona->fresh()->direccion_normalizada);
        $this->assertNull($persona->fresh()->seccion_censal_codigo);
        $this->assertNull($this->servicio->vigente($persona, $this->tipoCss));
    }
}
