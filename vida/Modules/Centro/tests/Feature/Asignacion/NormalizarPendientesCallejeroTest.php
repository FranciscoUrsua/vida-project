<?php

namespace Modules\Centro\Tests\Feature\Asignacion;

use App\Enums\OrigenDireccion;
use App\Models\Ciudadano;
use App\Services\Geocodificacion\Callejero\ImportadorCallejero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Centro\Services\Asignacion\AsignacionCentroService;
use Modules\Centro\Tests\Concerns\AsignacionTestSetup;
use Modules\Organizacion\Models\Configuracion;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FicheroCallejeroDePrueba as Fichero;
use Tests\TestCase;

/**
 * Normalización de las direcciones ya guardadas con el callejero (TF-CAL-13 a 15, paso 4).
 *
 * `vida:normalizar-direcciones --pendientes` pasa por el adaptador las direcciones de
 * texto libre guardadas cuando no había geocodificador. Una que casa queda con los
 * códigos territoriales y dispara la asignación de centro, igual que al guardarla;
 * las del padrón no se tocan (geocodificacion.md §3.3).
 *
 * @see docs/instrucciones-cli/2026-11-callejero-madrid.md
 */
class NormalizarPendientesCallejeroTest extends TestCase
{
    use AsignacionTestSetup;
    use RefreshDatabase;

    /**
     * Escenario de asignación y callejero con la calle Mayor 15 en la sección 2807901001
     * (barrio 011, CSS Norte). Sin geocodificador al principio.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEscenarioAsignacion();

        $ruta = Fichero::escribir([Fichero::fila(['Codigo de barrio' => '01', 'Seccion censal' => '001'])], $this->carpetaTemporalDeTest('callejero').'/callejero.csv');
        app(ImportadorCallejero::class)->importar($ruta, 'local', $ruta);

        Configuracion::create(['clave' => 'geocoder.proveedor', 'valor' => 'ninguno', 'tipo' => 'texto']);
    }

    /**
     * Persona guardada con una dirección sin normalizar.
     *
     * @param string $direccion
     * @param OrigenDireccion|null $origen
     *
     * @return Ciudadano
     */
    private function personaPendiente(string $direccion, ?OrigenDireccion $origen = OrigenDireccion::Profesional): Ciudadano
    {
        $persona = Ciudadano::factory()->create(['direccion_texto' => $direccion, 'origen_direccion' => $origen]);
        $this->assertFalse($persona->fresh()->direccion_normalizada);

        return $persona;
    }

    /**
     * Activa el callejero y ejecuta el comando sobre las pendientes.
     *
     * @return void
     */
    private function normalizarPendientes(): void
    {
        Configuracion::where('clave', 'geocoder.proveedor')->update(['valor' => 'callejero']);
        $this->artisan('vida:normalizar-direcciones', ['--entidad' => 'ciudadano', '--pendientes' => true])->assertExitCode(0);
    }

    #[Test]
    public function tf_cal_13_una_direccion_pendiente_queda_normalizada_y_asignada_por_la_seccion(): void
    {
        $persona = $this->personaPendiente('Calle Mayor 15, 2º A');

        $this->normalizarPendientes();

        $persona = $persona->fresh();
        $this->assertTrue($persona->direccion_normalizada);
        $this->assertSame('callejero', $persona->geocoder_proveedor);
        $this->assertSame(OrigenDireccion::Geocodificacion, $persona->origen_direccion);
        $this->assertSame('20104713', $persona->codigo_ndp);
        $this->assertSame('011', $persona->barrio_codigo);
        $this->assertSame('2807901001', $persona->seccion_censal_codigo);
        $this->assertSame('2', $persona->piso);
        // El texto escrito se conserva
        $this->assertSame('Calle Mayor 15, 2º A', $persona->direccion_texto);

        $vigente = app(AsignacionCentroService::class)->vigente($persona, $this->tipoCss);
        $this->assertSame($this->cssNorte->id, $vigente?->centro_id);
    }

    #[Test]
    public function tf_cal_14_las_que_no_casan_quedan_como_estaban(): void
    {
        $persona = $this->personaPendiente('Calle Mayor 99');

        $this->normalizarPendientes();

        $persona = $persona->fresh();
        $this->assertFalse($persona->direccion_normalizada);
        $this->assertNull($persona->seccion_censal_codigo);
        $this->assertSame(OrigenDireccion::Profesional, $persona->origen_direccion);
        $this->assertNull(app(AsignacionCentroService::class)->vigente($persona, $this->tipoCss));
    }

    #[Test]
    public function tf_cal_15_sin_origen_se_normaliza_y_las_del_padron_no_se_tocan(): void
    {
        $sinOrigen = $this->personaPendiente('Calle Mayor 15', null);
        $delPadron = $this->personaPendiente('Calle Mayor 15', OrigenDireccion::Padron);
        // El observer no actúa sobre el padrón: se fuerza el estado de una dirección sin normalizar
        DB::table('ciudadanos')->where('id', $delPadron->id)->update(['direccion_normalizada' => false]);

        $this->normalizarPendientes();

        $this->assertTrue($sinOrigen->fresh()->direccion_normalizada);
        $this->assertFalse($delPadron->fresh()->direccion_normalizada);
        $this->assertNull($delPadron->fresh()->seccion_censal_codigo);
    }
}
