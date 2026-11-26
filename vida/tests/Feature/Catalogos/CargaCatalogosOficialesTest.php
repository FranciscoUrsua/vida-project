<?php

namespace Tests\Feature\Catalogos;

use App\Models\CatalogoSistema;
use App\Services\Catalogos\CargaCatalogosOficiales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Catálogos versionados y carga desde fuentes oficiales (TF-SDE-01 a 08).
 *
 * Los tests de carga usan CSV pequeños de prueba en un grupo propio (`prueba.pais`), no
 * los oficiales, salvo TF-SDE-07, que comprueba lo que deja la migración de datos.
 *
 * @see docs/instrucciones-cli/2026-11-ficha-sociodemografica.md, bloque A
 */
class CargaCatalogosOficialesTest extends TestCase
{
    use RefreshDatabase;

    private const GRUPO = 'prueba.pais';

    private string $directorio;

    /**
     * Carpeta temporal para los CSV de prueba.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->directorio = storage_path('framework/testing/catalogos-'.getmypid().'-'.uniqid());
        File::ensureDirectoryExists($this->directorio);
    }

    /**
     * Borra la carpeta temporal.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        File::deleteDirectory($this->directorio);
        parent::tearDown();
    }

    #[Test]
    public function tf_sde_01_cargar_un_catalogo_crea_las_filas_con_todos_sus_datos(): void
    {
        $this->csv('paises.csv', [$this->fila('ESP', 'España'), $this->fila('MAR', 'Marruecos', ue: false)]);

        $resultado = $this->carga()->cargar(self::GRUPO, 'paises.csv');

        $this->assertSame(2, $resultado->creados);
        $espana = CatalogoSistema::where('grupo', self::GRUPO)->where('clave', 'ESP')->sole();
        $this->assertSame('España', $espana->etiqueta);
        $this->assertTrue($espana->activo);
        $this->assertEqualsCanonicalizing(['region' => 'Europa', 'ue' => true], $espana->atributos);
        $this->assertSame('ES', $espana->equivalencias['iso2']);
        $this->assertArrayHasKey('ine', $espana->equivalencias);
        $this->assertSame('Fuente de prueba v1', $espana->fuente);
        $this->assertNull($espana->vigente_hasta);
    }

    #[Test]
    public function tf_sde_02_cargar_dos_veces_el_mismo_fichero_no_duplica_ni_cambia_nada(): void
    {
        $this->csv('paises.csv', [$this->fila('ESP', 'España'), $this->fila('MAR', 'Marruecos', ue: false)]);
        $this->carga()->cargar(self::GRUPO, 'paises.csv');

        $segunda = $this->carga()->cargar(self::GRUPO, 'paises.csv');

        $this->assertSame(0, $segunda->creados);
        // jsonb reordena las claves: aun así no debe contar como cambio
        $this->assertSame(0, $segunda->actualizados);
        $this->assertSame(0, $segunda->sinVigencia);
        $this->assertSame(2, CatalogoSistema::where('grupo', self::GRUPO)->count());
    }

    #[Test]
    public function tf_sde_03_un_cambio_de_etiqueta_actualiza_sin_cambiar_clave_ni_id(): void
    {
        $this->csv('paises.csv', [$this->fila('ESP', 'España')]);
        $this->carga()->cargar(self::GRUPO, 'paises.csv');
        $id = CatalogoSistema::where('grupo', self::GRUPO)->where('clave', 'ESP')->value('id');

        $this->csv('paises.csv', [$this->fila('ESP', 'Reino de España')]);
        $resultado = $this->carga()->cargar(self::GRUPO, 'paises.csv');

        $this->assertSame(1, $resultado->actualizados);
        $valor = CatalogoSistema::find($id);
        $this->assertSame('ESP', $valor->clave);
        $this->assertSame('Reino de España', $valor->etiqueta);
        $this->assertSame(1, CatalogoSistema::where('grupo', self::GRUPO)->count());
    }

    #[Test]
    public function tf_sde_04_un_valor_desactivado_a_mano_no_se_reactiva_al_volver_a_cargar(): void
    {
        $this->csv('paises.csv', [$this->fila('ESP', 'España'), $this->fila('MAR', 'Marruecos')]);
        $this->carga()->cargar(self::GRUPO, 'paises.csv');
        CatalogoSistema::where('grupo', self::GRUPO)->where('clave', 'MAR')->update(['activo' => false]);

        $this->csv('paises.csv', [$this->fila('ESP', 'España'), $this->fila('MAR', 'Reino de Marruecos')]);
        $this->carga()->cargar(self::GRUPO, 'paises.csv');

        $marruecos = CatalogoSistema::where('grupo', self::GRUPO)->where('clave', 'MAR')->sole();
        $this->assertFalse($marruecos->activo);
        // Lo demás sí se actualiza
        $this->assertSame('Reino de Marruecos', $marruecos->etiqueta);
        $this->assertArrayNotHasKey('MAR', CatalogoSistema::opcionesParaSelect(self::GRUPO));
    }

    #[Test]
    public function tf_sde_05_un_valor_que_desaparece_no_se_borra_y_deja_de_estar_vigente(): void
    {
        $this->csv('paises.csv', [$this->fila('ESP', 'España'), $this->fila('YUG', 'Yugoslavia')]);
        $this->carga()->cargar(self::GRUPO, 'paises.csv');

        $this->csv('paises.csv', [$this->fila('ESP', 'España')]);
        $resultado = $this->carga()->cargar(self::GRUPO, 'paises.csv');

        $this->assertSame(1, $resultado->sinVigencia);
        $yugoslavia = CatalogoSistema::where('grupo', self::GRUPO)->where('clave', 'YUG')->sole();
        $this->assertNotNull($yugoslavia->vigente_hasta);
        $this->assertTrue($yugoslavia->vigente_hasta->lt(today()));
        $this->assertTrue($yugoslavia->activo, 'Dejar de estar vigente no es desactivar.');
        $this->assertSame(['ESP' => 'España'], CatalogoSistema::opcionesParaSelect(self::GRUPO));

        // Una tercera carga no vuelve a contarlo ni le cambia la fecha
        $this->assertSame(0, $this->carga()->cargar(self::GRUPO, 'paises.csv')->sinVigencia);

        // Si vuelve a la fuente, recupera la vigencia
        $this->csv('paises.csv', [$this->fila('ESP', 'España'), $this->fila('YUG', 'Yugoslavia')]);
        $this->carga()->cargar(self::GRUPO, 'paises.csv');
        $this->assertArrayHasKey('YUG', CatalogoSistema::opcionesParaSelect(self::GRUPO));
    }

    #[Test]
    public function tf_sde_06_fichero_ausente_o_con_errores_lanza_excepcion_sin_datos_a_medias(): void
    {
        $this->assertLanza(fn () => $this->carga()->cargar(self::GRUPO, 'no-existe.csv'), 'No se encuentra');

        $this->csv('duplicado.csv', [$this->fila('ESP', 'España'), $this->fila('MAR', 'Marruecos'), $this->fila('ESP', 'Otra España')]);
        $this->assertLanza(fn () => $this->carga()->cargar(self::GRUPO, 'duplicado.csv'), 'aparece dos veces');

        $filas = [$this->fila('ESP', 'España'), $this->fila('MAR', 'Marruecos')];
        $filas[1][3] = '{no es json';
        $this->csv('json.csv', $filas);
        $this->assertLanza(fn () => $this->carga()->cargar(self::GRUPO, 'json.csv'), 'JSON inválido');

        $this->assertSame(0, CatalogoSistema::where('grupo', self::GRUPO)->count(), 'Ningún fichero erróneo deja filas.');

        // todos(): si falta un fichero no se carga ninguno, aunque los anteriores estén bien
        $this->csv(CargaCatalogosOficiales::FICHEROS['comun.pais'], [$this->fila('ESP', 'Etiqueta que no debe llegar')]);
        $this->assertLanza(fn () => $this->carga()->todos(), 'No se encuentra');
        $this->assertSame('España', CatalogoSistema::etiquetaDe('comun.pais', 'ESP'));
    }

    #[Test]
    public function tf_sde_07_la_migracion_de_datos_deja_los_cuatro_catalogos_oficiales(): void
    {
        foreach (array_keys(CargaCatalogosOficiales::FICHEROS) as $grupo) {
            $this->assertGreaterThan(1, CatalogoSistema::where('grupo', $grupo)->count(), "Falta el catálogo {$grupo}.");
        }

        $espana = CatalogoSistema::where('grupo', 'comun.pais')->where('clave', 'ESP')->sole();
        $this->assertSame('España', $espana->etiqueta);
        $this->assertSame('ES', $espana->equivalencias['iso2']);
        $this->assertSame('724', $espana->equivalencias['m49']);
        $this->assertTrue($espana->atributos['ue']);
        $this->assertFalse(CatalogoSistema::where('grupo', 'comun.pais')->where('clave', 'GBR')->sole()->atributos['ue']);
        $this->assertSame(27, CatalogoSistema::where('grupo', 'comun.pais')->where('atributos->ue', true)->count());

        $this->assertSame('010', CatalogoSistema::where('grupo', 'comun.nivel_estudios')->where('clave', '01')->sole()->equivalencias['cine2011']);
        $this->assertSame('ocupado', CatalogoSistema::where('grupo', 'comun.situacion_laboral')->where('clave', 'SIDAC-1')->sole()->atributos['grupo']);
        $this->assertSame('Español', CatalogoSistema::etiquetaDe('comun.idioma', 'es'));
        // Idioma sin código de dos letras: clave ISO 639-2
        $this->assertSame('rom', CatalogoSistema::where('grupo', 'comun.idioma')->where('equivalencias->iso639_3', 'rom')->value('clave'));

        // Las claves especiales de la OACI (XXA, XXX…) quedan fuera hasta tener el Doc 9303
        // (decisión del desarrollador del 26 nov 2026, BACKLOG)
    }

    #[Test]
    public function tf_sde_07_bis_los_csv_versionados_salen_de_los_originales_con_el_comando(): void
    {
        Artisan::call('catalogos:normalizar', ['--destino' => $this->directorio, '--fuentes' => database_path('data/catalogos/fuentes')]);

        foreach (CargaCatalogosOficiales::FICHEROS as $fichero) {
            $this->assertFileEquals(database_path('data/catalogos/'.$fichero), $this->directorio.'/'.$fichero, "{$fichero} no coincide con lo que genera catalogos:normalizar.");
        }
    }

    #[Test]
    public function tf_sde_08_etiqueta_de_muestra_valores_no_vigentes_y_vigentes_los_excluye(): void
    {
        CatalogoSistema::create(['grupo' => self::GRUPO, 'clave' => 'ESP', 'etiqueta' => 'España']);
        CatalogoSistema::create(['grupo' => self::GRUPO, 'clave' => 'YUG', 'etiqueta' => 'Yugoslavia', 'vigente_hasta' => today()->subYear()]);
        CatalogoSistema::create(['grupo' => self::GRUPO, 'clave' => 'OFF', 'etiqueta' => 'Inactivo', 'activo' => false]);
        CatalogoSistema::create(['grupo' => self::GRUPO, 'clave' => 'FUT', 'etiqueta' => 'Futuro', 'vigente_desde' => today()->addMonth()]);

        $this->assertSame('Yugoslavia', CatalogoSistema::etiquetaDe(self::GRUPO, 'YUG'));
        $this->assertSame('Inactivo', CatalogoSistema::etiquetaDe(self::GRUPO, 'OFF'));
        $this->assertNull(CatalogoSistema::etiquetaDe(self::GRUPO, 'XYZ'));

        $this->assertSame(['ESP'], CatalogoSistema::where('grupo', self::GRUPO)->vigentes()->pluck('clave')->all());
        // En una fecha pasada, Yugoslavia sí estaba vigente; el futuro no
        $this->assertEqualsCanonicalizing(
            ['ESP', 'YUG'],
            CatalogoSistema::where('grupo', self::GRUPO)->vigentes(today()->subYears(2))->pluck('clave')->all(),
        );
        $this->assertSame(['ESP' => 'España'], CatalogoSistema::opcionesParaSelect(self::GRUPO));
    }

    /**
     * Servicio de carga sobre la carpeta temporal.
     *
     * @return CargaCatalogosOficiales
     */
    private function carga(): CargaCatalogosOficiales
    {
        return new CargaCatalogosOficiales($this->directorio);
    }

    /**
     * Fila de país de prueba en el formato normalizado.
     *
     * @param string $clave Código.
     * @param string $etiqueta Nombre.
     * @param bool $ue Si es de la UE.
     *
     * @return list<string>
     */
    private function fila(string $clave, string $etiqueta, bool $ue = true): array
    {
        return [
            $clave, $etiqueta, '1',
            json_encode(['ue' => $ue, 'region' => 'Europa']),
            json_encode(['iso2' => substr($clave, 0, 2), 'ine' => null]),
            'Fuente de prueba v1',
        ];
    }

    /**
     * Escribe un CSV de prueba con la cabecera normalizada.
     *
     * @param string $fichero Nombre del fichero.
     * @param list<list<string>> $filas Filas.
     *
     * @return void
     */
    private function csv(string $fichero, array $filas): void
    {
        $manejador = fopen($this->directorio.'/'.$fichero, 'w');
        fputcsv($manejador, CargaCatalogosOficiales::CABECERA, ';', '"', '');
        foreach ($filas as $fila) {
            fputcsv($manejador, $fila, ';', '"', '');
        }
        fclose($manejador);
    }

    /**
     * Comprueba que la llamada lanza una RuntimeException con ese texto.
     *
     * @param callable $llamada Código a ejecutar.
     * @param string $texto Fragmento del mensaje.
     *
     * @return void
     */
    private function assertLanza(callable $llamada, string $texto): void
    {
        try {
            $llamada();
            $this->fail("Se esperaba una excepción con «{$texto}».");
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($texto, $e->getMessage());
        }
    }
}
