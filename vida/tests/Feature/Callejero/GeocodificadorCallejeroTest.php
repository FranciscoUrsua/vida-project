<?php

namespace Tests\Feature\Callejero;

use App\Enums\OrigenDireccion;
use App\Enums\TipoNumeracion;
use App\Jobs\NormalizarDireccionJob;
use App\Models\Ciudadano;
use App\Services\Geocodificacion\Adaptadores\CallejeroMadridGeocodificador;
use App\Services\Geocodificacion\Callejero\ImportadorCallejero;
use App\Services\Geocodificacion\GeocodificadorInterface;
use App\Services\Geocodificacion\GeocodificadorService;
use App\Services\Geocodificacion\ResultadoGeocodificacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Organizacion\Models\Configuracion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FicheroCallejeroDePrueba as Fichero;
use Tests\TestCase;

/**
 * Geocodificador local sobre el callejero oficial de Madrid (TF-CAL-04 a 09).
 *
 * Una dirección escrita a mano se normaliza solo si casa con un único número del
 * callejero; si es ambigua, no existe o es de otro municipio, queda sin normalizar
 * (y la asignación de centro, pendiente): mejor un caso pendiente que una asignación
 * equivocada (decisión 3 de las instrucciones).
 *
 * @see docs/instrucciones-cli/2026-11-callejero-madrid.md
 */
class GeocodificadorCallejeroTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Carga un callejero pequeño: calle Mayor 15 y 17, calle y plaza del Alamillo 3
     * (homónimas) y avenida de la Albufera 120. Ningún test sale a la red.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();

        $this->cargarCallejero([
            Fichero::fila(),
            Fichero::fila(['Codigo de numero' => '20104714', 'Literal de numeracion' => 'NUM000017', 'Direccion completa para el numero' => 'MAYOR, 000017']),
            Fichero::fila([
                'Codigo de numero' => '31001001', 'Codigo de via' => '500100', 'Nombre de la vía' => 'ALAMILLO',
                'Partícula de la vía' => 'DEL', 'Literal de numeracion' => 'NUM000003', 'Direccion completa para el numero' => 'ALAMILLO, 000003',
            ]),
            Fichero::fila([
                'Codigo de numero' => '31001002', 'Codigo de via' => '500200', 'Clase de la via' => 'PLAZA', 'Nombre de la vía' => 'ALAMILLO',
                'Partícula de la vía' => 'DEL', 'Literal de numeracion' => 'NUM000003', 'Direccion completa para el numero' => 'PZ ALAMILLO, 000003',
                'Tipologia del numero' => 'Frente fachada',
            ]),
            Fichero::fila([
                'Codigo de numero' => '13012001', 'Codigo de via' => '120300', 'Clase de la via' => 'AVENIDA', 'Partícula de la vía' => 'DE LA',
                'Nombre de la vía' => 'ALBUFERA', 'Literal de numeracion' => 'NUM000120', 'Direccion completa para el numero' => 'AVDA ALBUFERA, 000120',
                'Codigo de distrito' => '13', 'Codigo de barrio' => '02', 'Seccion censal' => '045', 'Codigo postal' => '28038',
            ]),
        ]);
    }

    /**
     * Importa las filas como callejero vigente.
     *
     * @param list<array<string, string>> $filas
     *
     * @return void
     */
    private function cargarCallejero(array $filas): void
    {
        $ruta = Fichero::escribir($filas, $this->carpetaTemporalDeTest('callejero').'/callejero.csv');
        app(ImportadorCallejero::class)->importar($ruta, 'local', $ruta);
    }

    /**
     * Normaliza con el adaptador del callejero.
     *
     * @param string $texto
     *
     * @return ResultadoGeocodificacion
     */
    private function normalizar(string $texto): ResultadoGeocodificacion
    {
        return app(CallejeroMadridGeocodificador::class)->normalizar($texto);
    }

    #[Test]
    public function tf_cal_04_una_direccion_exacta_queda_normalizada_con_los_datos_del_callejero(): void
    {
        Configuracion::create(['clave' => 'geocoder.proveedor', 'valor' => 'callejero', 'tipo' => 'texto']);
        $this->assertSame('callejero', GeocodificadorService::proveedorActivo());

        $ciudadano = Ciudadano::create([
            'nombre' => 'Ana',
            'apellido1' => 'Prueba',
            'fecha_nacimiento' => '1980-05-05',
            'sexo' => 'mujer',
            'direccion_texto' => 'Calle Mayor 15',
            'origen_direccion' => OrigenDireccion::Profesional,
            'activo' => true,
        ])->fresh();

        $this->assertTrue($ciudadano->direccion_normalizada);
        $this->assertSame('callejero', $ciudadano->geocoder_proveedor);
        $this->assertSame('20104713', $ciudadano->codigo_ndp);
        $this->assertSame('01', $ciudadano->distrito_codigo);
        $this->assertSame('016', $ciudadano->barrio_codigo);
        $this->assertSame('2807901028', $ciudadano->seccion_censal_codigo);
        $this->assertSame('Calle', $ciudadano->tipo_via);
        $this->assertSame('Mayor', $ciudadano->nombre_via);
        $this->assertSame('15', $ciudadano->numero);
        $this->assertSame(TipoNumeracion::Numero, $ciudadano->tipo_numeracion);
        $this->assertSame('28013', $ciudadano->codigo_postal);
        $this->assertSame('Madrid', $ciudadano->municipio);
        $this->assertEqualsWithDelta(40 + 24 / 60 + 54.12 / 3600, (float) $ciudadano->coordenadas_lat, 0.00001);
        // El texto escrito se conserva siempre
        $this->assertSame('Calle Mayor 15', $ciudadano->direccion_texto);
    }

    /**
     * Formas de escribir la calle Mayor 15.
     *
     * @return array<string, array{string}>
     */
    public static function variantesMayor15(): array
    {
        return [
            'abreviada' => ['c/ mayor, 15'],
            'sin espacio' => ['C/Mayor 15'],
            'mayúsculas con piso' => ['CALLE MAYOR 15 2º B'],
            'sin tipo de vía' => ['Mayor 15'],
            'con código postal y municipio' => ['Calle Mayor, 15, 28013 Madrid'],
        ];
    }

    #[Test]
    #[DataProvider('variantesMayor15')]
    public function tf_cal_05_las_variantes_de_escritura_casan_con_el_mismo_portal(string $texto): void
    {
        $resultado = $this->normalizar($texto);

        $this->assertTrue($resultado->exito, (string) $resultado->errorMensaje);
        $this->assertSame('20104713', $resultado->codigoNdp);
        $this->assertSame('2807901028', $resultado->seccionCensal);
    }

    #[Test]
    public function tf_cal_05_bis_se_conservan_piso_y_puerta_y_la_particula_no_estorba(): void
    {
        $conPiso = $this->normalizar('CALLE MAYOR 15 2º B');
        $this->assertSame('2', $conPiso->piso);
        $this->assertSame('B', $conPiso->puerta);

        $albufera = $this->normalizar('Avda. de la Albufera 120, 3º izq');
        $this->assertTrue($albufera->exito, (string) $albufera->errorMensaje);
        $this->assertSame('13012001', $albufera->codigoNdp);
        $this->assertSame('Avenida', $albufera->tipoVia);
        $this->assertSame('de la Albufera', $albufera->nombreVia);

        $bajo = $this->normalizar('Calle Mayor 17 bajo izq');
        $this->assertSame('bajo', $bajo->piso);
        $this->assertSame('izq', $bajo->puerta);
        $this->assertSame('3', $albufera->piso);
        $this->assertSame('izq', $albufera->puerta);
    }

    #[Test]
    public function tf_cal_06_una_errata_leve_casa_por_similitud(): void
    {
        $resultado = $this->normalizar('Calle Mayro 15');

        $this->assertTrue($resultado->exito, (string) $resultado->errorMensaje);
        $this->assertSame('20104713', $resultado->codigoNdp);
    }

    #[Test]
    public function tf_cal_06_bis_una_errata_a_la_misma_distancia_de_dos_vias_no_se_normaliza(): void
    {
        // En el callejero real existen la calle Mayo y la calle Mayor: «Mayro» está a una
        // letra de las dos. Elegir la más parecida por trigramas daría Mayo.
        $this->cargarCallejero([
            Fichero::fila(),
            Fichero::fila(['Codigo de numero' => '20200015', 'Codigo de via' => '600100', 'Nombre de la vía' => 'MAYO', 'Direccion completa para el numero' => 'MAYO, 000015', 'Seccion censal' => '099']),
        ]);

        $resultado = $this->normalizar('Calle Mayro 15');

        $this->assertFalse($resultado->exito);
        $this->assertNull($resultado->seccionCensal);
        $this->assertStringContainsString('ambigua', (string) $resultado->errorMensaje);
    }

    #[Test]
    public function tf_cal_07_una_via_homonima_sin_clase_no_se_normaliza(): void
    {
        $ambigua = $this->normalizar('Alamillo 3');

        $this->assertFalse($ambigua->exito);
        $this->assertNull($ambigua->seccionCensal);
        $this->assertNull($ambigua->codigoNdp);
        $this->assertStringContainsString('ambigua', (string) $ambigua->errorMensaje);

        // Con la clase sí se distingue
        $this->assertSame('31001002', $this->normalizar('Plaza del Alamillo 3')->codigoNdp);
        $this->assertSame('31001001', $this->normalizar('Calle del Alamillo, 3')->codigoNdp);
    }

    #[Test]
    public function tf_cal_08_sin_numero_o_con_un_numero_inexistente_no_se_normaliza(): void
    {
        foreach (['Calle Mayor', 'Calle Mayor s/n', 'Calle Mayor 99', 'Calle Mayor 15B'] as $texto) {
            $resultado = $this->normalizar($texto);

            $this->assertFalse($resultado->exito, $texto);
            $this->assertNull($resultado->seccionCensal, $texto);
        }

        $this->assertTrue($this->normalizar('Calle Mayor 17')->exito);
    }

    #[Test]
    public function tf_cal_08_bis_un_numero_inexistente_de_una_via_que_existe_no_se_busca_en_otra_parecida(): void
    {
        // La calle Mayor existe pero no tiene el 99; la calle Mayo sí. No se buscan erratas.
        $this->cargarCallejero([
            Fichero::fila(),
            Fichero::fila(['Codigo de numero' => '20200099', 'Codigo de via' => '600100', 'Nombre de la vía' => 'MAYO', 'Literal de numeracion' => 'NUM000099', 'Direccion completa para el numero' => 'MAYO, 000099']),
        ]);

        $resultado = $this->normalizar('Calle Mayor 99');

        $this->assertFalse($resultado->exito);
        $this->assertNull($resultado->codigoNdp);
        $this->assertSame('20200099', $this->normalizar('Calle Mayo 99')->codigoNdp);
    }

    #[Test]
    public function tf_cal_08_ter_el_reintento_de_una_direccion_que_no_casa_no_deja_un_trabajo_fallido(): void
    {
        Configuracion::create(['clave' => 'geocoder.proveedor', 'valor' => 'callejero', 'tipo' => 'texto']);
        $ciudadano = Ciudadano::create([
            'nombre' => 'Luis', 'apellido1' => 'Prueba', 'fecha_nacimiento' => '1970-01-01', 'sexo' => 'hombre',
            'direccion_texto' => 'Alamillo 3', 'origen_direccion' => OrigenDireccion::Profesional, 'activo' => true,
        ]);
        $this->assertFalse($ciudadano->fresh()->direccion_normalizada);

        $job = (new NormalizarDireccionJob(Ciudadano::class, $ciudadano->id))->withFakeQueueInteractions();
        $job->handle(app(GeocodificadorInterface::class));

        $job->assertNotFailed();
        $this->assertFalse($ciudadano->fresh()->direccion_normalizada);
    }

    #[Test]
    public function tf_cal_09_una_direccion_de_otro_municipio_no_se_normaliza(): void
    {
        foreach (['Calle Mayor 15, Alcalá de Henares', 'Calle Mayor 15, 28801 Alcalá de Henares', 'C/ Mayor 15, 28805'] as $texto) {
            $resultado = $this->normalizar($texto);

            $this->assertFalse($resultado->exito, $texto);
            $this->assertNull($resultado->seccionCensal, $texto);
        }

        $this->assertTrue($this->normalizar('Calle Mayor 15, Madrid')->exito);
    }

    #[Test]
    public function tf_cal_11_parcial_sin_domicilio_fijo_queda_sin_normalizar_y_sin_error(): void
    {
        $resultado = $this->normalizar('Sin domicilio fijo');

        $this->assertFalse($resultado->exito);
        $this->assertNull($resultado->seccionCensal);
    }
}
