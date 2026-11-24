<?php

namespace Tests\Feature\Callejero;

use App\Enums\TipoNumeracion;
use App\Enums\TipoNumeroCallejero;
use App\Models\Callejero\CallejeroImportacion;
use App\Models\Callejero\CallejeroPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FicheroCallejeroDePrueba as Fichero;
use Tests\TestCase;

/**
 * Importación del callejero oficial de Madrid (TF-CAL-01 a 03).
 *
 * El comando `callejero:importar` carga el fichero de números del conjunto 200075 en
 * `callejero_portales`, que es lo que consulta el geocodificador local. Los ficheros de
 * prueba tienen el formato real (latin-1, `;`, comillas y relleno de espacios).
 *
 * @see docs/instrucciones-cli/2026-11-callejero-madrid.md
 */
class ImportacionCallejeroTest extends TestCase
{
    use RefreshDatabase;

    /** Tolerancia de 1 m expresada en grados (1 m ≈ 0,000009° de latitud en Madrid). */
    private const TOLERANCIA_GRADOS = 0.00001;

    /** Carpeta temporal de este proceso para los ficheros de prueba. */
    private string $carpeta;

    /**
     * Prohíbe peticiones HTTP no simuladas: ningún test sale a la red.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->carpeta = $this->carpetaTemporalDeTest('callejero');
    }

    /**
     * Los tres números de prueba: un portal, un frente de fachada con calificador y una
     * parcela con sección `000` y tildes en la dirección completa.
     *
     * @return list<array<string, string>>
     */
    private function tresNumeros(): array
    {
        return [
            Fichero::fila(),
            Fichero::fila([
                'Codigo de numero' => '13012001', 'Codigo de via' => '120300', 'Clase de la via' => 'AVENIDA',
                'Partícula de la vía' => 'DE LA', 'Nombre de la vía' => 'ALBUFERA', 'Literal de numeracion' => 'NUM000120 B',
                'Codigo de distrito' => '13', 'Codigo de barrio' => '02', 'Seccion censal' => '045', 'Codigo postal' => '28038',
                'Direccion completa para el numero' => 'AVDA ALBUFERA, 000120 B',
                'Longitud en S R  ETRS89 WGS84' => "3º39'51.2'' W", 'Latitud en S R  ETRS89 WGS84' => "40º23'34.9'' N",
                'Tipologia del numero' => 'Frente fachada',
            ]),
            Fichero::fila([
                'Codigo de numero' => '31074723', 'Codigo de via' => '100100', 'Clase de la via' => 'CALLE',
                'Partícula de la vía' => 'DEL', 'Nombre de la vía' => 'ABAD JUAN CATALAN', 'Literal de numeracion' => 'NUM000001',
                'Codigo de distrito' => '20', 'Codigo de barrio' => '01', 'Seccion censal' => '000', 'Codigo postal' => '28032',
                'Direccion completa para el numero' => 'ABAD JUAN CATALÁN, 000001',
                'Longitud en S R  ETRS89 WGS84' => "3º35'39.52'' W", 'Latitud en S R  ETRS89 WGS84' => "40º24'14.52'' N",
                'Tipologia del numero' => 'Parcela',
            ]),
        ];
    }

    #[Test]
    public function tf_cal_01_importa_los_numeros_con_sus_codigos_y_coordenadas(): void
    {
        $ruta = Fichero::escribir($this->tresNumeros(), "{$this->carpeta}/callejero_vigente_numeraciones_202611.csv");

        $this->artisan('callejero:importar', ['--fichero' => $ruta])->assertExitCode(0);

        $this->assertSame(3, CallejeroPortal::count());

        $mayor = CallejeroPortal::where('cod_ndp', '20104713')->firstOrFail();
        $this->assertSame('471300', $mayor->cod_via);
        $this->assertSame('CALLE', $mayor->clase_via);
        $this->assertNull($mayor->particula_via);
        $this->assertSame('MAYOR', $mayor->nombre_via);
        $this->assertSame('MAYOR', $mayor->nombre_via_mostrar);
        $this->assertSame('mayor', $mayor->nombre_via_busqueda);
        $this->assertSame(TipoNumeracion::Numero, $mayor->clase_numero);
        $this->assertSame('15', $mayor->numero);
        $this->assertNull($mayor->calificador);
        $this->assertSame(TipoNumeroCallejero::Portal, $mayor->tipo_numero);
        $this->assertSame('28013', $mayor->codigo_postal);
        $this->assertSame('01', $mayor->distrito_codigo);
        $this->assertSame('016', $mayor->barrio_codigo);
        $this->assertSame('2807901028', $mayor->seccion_censal_codigo);
        // 3º42'27.5'' W, 40º24'54.12'' N
        $this->assertEqualsWithDelta(-(3 + 42 / 60 + 27.5 / 3600), $mayor->lng, self::TOLERANCIA_GRADOS);
        $this->assertEqualsWithDelta(40 + 24 / 60 + 54.12 / 3600, $mayor->lat, self::TOLERANCIA_GRADOS);
        $this->assertNotNull($mayor->importado_en);

        $albufera = CallejeroPortal::where('cod_ndp', '13012001')->firstOrFail();
        $this->assertSame('DE LA', $albufera->particula_via);
        $this->assertSame('ALBUFERA', $albufera->nombre_via_mostrar);
        $this->assertSame('120', $albufera->numero);
        $this->assertSame('B', $albufera->calificador);
        $this->assertSame(TipoNumeroCallejero::FrenteFachada, $albufera->tipo_numero);
        $this->assertSame('132', $albufera->barrio_codigo);
        $this->assertSame('2807913045', $albufera->seccion_censal_codigo);

        // Sección 000: sin sección; el nombre para mostrar conserva las tildes
        $abad = CallejeroPortal::where('cod_ndp', '31074723')->firstOrFail();
        $this->assertNull($abad->seccion_censal_codigo);
        $this->assertSame('ABAD JUAN CATALÁN', $abad->nombre_via_mostrar);
        $this->assertSame('abad juan catalan', $abad->nombre_via_busqueda);
        $this->assertSame(TipoNumeroCallejero::Parcela, $abad->tipo_numero);
        $this->assertSame('201', $abad->barrio_codigo);

        $importacion = CallejeroImportacion::sole();
        $this->assertSame('202611', $importacion->version);
        $this->assertSame(3, $importacion->numeros);
    }

    #[Test]
    public function tf_cal_01_bis_kilometros_y_calificador_enye(): void
    {
        $ruta = Fichero::escribir([
            Fichero::fila([
                'Codigo de numero' => '31031089', 'Clase de la via' => 'AUTOVIA', 'Nombre de la vía' => 'A-1',
                'Literal de numeracion' => 'KM.001000EN', 'Direccion completa para el numero' => 'AUTOV A-1, 001000 EN',
                'Tipologia del numero' => 'Parcela',
            ]),
            Fichero::fila([
                'Codigo de numero' => '31061962', 'Nombre de la vía' => 'ADANERO',
                'Literal de numeracion' => 'NUM000008 Ñ', 'Direccion completa para el numero' => 'ADANERO, 000008 Ñ',
            ]),
        ], "{$this->carpeta}/literales.csv");

        $this->artisan('callejero:importar', ['--fichero' => $ruta])->assertExitCode(0);

        $enye = CallejeroPortal::where('cod_ndp', '31061962')->firstOrFail();
        $this->assertSame('8', $enye->numero);
        $this->assertSame('Ñ', $enye->calificador);

        $km = CallejeroPortal::where('cod_ndp', '31031089')->firstOrFail();
        $this->assertSame(TipoNumeracion::Km, $km->clase_numero);
        $this->assertSame('1000', $km->numero);
        $this->assertSame('EN', $km->calificador);
        $this->assertSame('A-1', $km->nombre_via_mostrar);
    }

    #[Test]
    public function tf_cal_01_ter_sin_fichero_resuelve_la_version_vigente_con_la_api_del_portal(): void
    {
        $contenido = file_get_contents(Fichero::escribir($this->tresNumeros(), "{$this->carpeta}/origen.csv"));
        $urlFichero = 'https://datos.madrid.es/dataset/200075-0-callejero/resource/200075-1-callejero-csv/download/callejero_vigente_numeraciones_202612.csv';

        Http::fake([
            'datos.madrid.es/api/3/action/package_show*' => Http::response(['success' => true, 'result' => ['resources' => [
                ['id' => '200075-0-callejero-txt', 'url' => 'https://datos.madrid.es/otro.txt'],
                ['id' => '200075-1-callejero-csv', 'url' => $urlFichero, 'last_modified' => '2026-12-16T15:25:13'],
            ]]]),
            $urlFichero => Http::response($contenido),
        ]);

        $this->artisan('callejero:importar')->assertExitCode(0);

        $this->assertSame(3, CallejeroPortal::count());
        $this->assertSame('202612', CallejeroImportacion::sole()->version);
        $this->assertSame($urlFichero, CallejeroImportacion::sole()->origen);
        Http::assertSent(fn (Request $peticion) => str_contains($peticion->url(), 'id=200075-0-callejero'));
    }

    #[Test]
    public function tf_cal_02_importar_dos_veces_es_idempotente_y_retira_lo_que_desaparece(): void
    {
        $numeros = $this->tresNumeros();
        $ruta = Fichero::escribir($numeros, "{$this->carpeta}/v1.csv");

        $this->artisan('callejero:importar', ['--fichero' => $ruta])->assertExitCode(0);
        $primera = CallejeroPortal::orderBy('cod_ndp')->get(['cod_ndp', 'numero', 'seccion_censal_codigo'])->toArray();

        $this->artisan('callejero:importar', ['--fichero' => $ruta])->assertExitCode(0);
        $this->assertSame($primera, CallejeroPortal::orderBy('cod_ndp')->get(['cod_ndp', 'numero', 'seccion_censal_codigo'])->toArray());

        // Un número que ya no viene en el fichero desaparece de la tabla
        $rutaSinAlbufera = Fichero::escribir([$numeros[0], $numeros[2]], "{$this->carpeta}/v2.csv");
        $this->artisan('callejero:importar', ['--fichero' => $rutaSinAlbufera])->assertExitCode(0);

        $this->assertEqualsCanonicalizing(['20104713', '31074723'], CallejeroPortal::pluck('cod_ndp')->all());
        $this->assertSame(3, CallejeroImportacion::count());
    }

    #[Test]
    public function tf_cal_03_con_otras_cabeceras_falla_sin_tocar_la_tabla(): void
    {
        $this->artisan('callejero:importar', ['--fichero' => Fichero::escribir($this->tresNumeros(), "{$this->carpeta}/bueno.csv")])
            ->assertExitCode(0);

        $cabecera = array_map(fn (string $c) => $c === 'Seccion censal' ? 'Seccion' : $c, Fichero::CABECERA);
        $filaNueva = Fichero::fila(['Codigo de numero' => '99999999', 'Seccion' => '001']);
        $ruta = Fichero::escribir([$filaNueva], "{$this->carpeta}/cambiado.csv", $cabecera);

        $this->artisan('callejero:importar', ['--fichero' => $ruta])
            ->expectsOutputToContain('Seccion censal')
            ->assertExitCode(1);

        $this->assertEqualsCanonicalizing(['20104713', '13012001', '31074723'], CallejeroPortal::pluck('cod_ndp')->all());
        $this->assertSame(1, CallejeroImportacion::count());
    }

    #[Test]
    public function tf_cal_03_bis_con_demasiadas_filas_ilegibles_falla_sin_tocar_la_tabla(): void
    {
        $this->artisan('callejero:importar', ['--fichero' => Fichero::escribir($this->tresNumeros(), "{$this->carpeta}/bueno.csv")])
            ->assertExitCode(0);

        // Si el portal cambia el formato del número o de las coordenadas, no se importa a medias
        $ilegibles = [
            Fichero::fila(['Codigo de numero' => '1', 'Literal de numeracion' => 'N.º 15']),
            Fichero::fila(['Codigo de numero' => '2', 'Latitud en S R  ETRS89 WGS84' => '40.4150']),
            Fichero::fila(['Codigo de numero' => '3']),
        ];
        $ruta = Fichero::escribir($ilegibles, "{$this->carpeta}/ilegible.csv");

        $this->artisan('callejero:importar', ['--fichero' => $ruta])->assertExitCode(1);

        $this->assertEqualsCanonicalizing(['20104713', '13012001', '31074723'], CallejeroPortal::pluck('cod_ndp')->all());
        $this->assertSame(1, CallejeroImportacion::count());
    }
}
