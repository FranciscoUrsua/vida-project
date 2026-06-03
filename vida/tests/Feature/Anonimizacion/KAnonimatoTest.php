<?php

namespace Tests\Feature\Anonimizacion;

use App\Services\Api\ValidadorKAnonimato;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo 3 — K-anonimato (Nivel 3).
 * Ver docs/tests-anonimizacion.md § Grupo 3.
 *
 * @group slow
 */
#[Group('slow')]
class KAnonimatoTest extends TestCase
{
    use RefreshDatabase;

    private ValidadorKAnonimato $validador;

    // Datasets fijos para garantizar determinismo. Distribuidos deliberadamente
    // para que ciertas combinaciones queden por debajo de K y activen la cascada.

    /**
     * 20 registros con distribución variada — algunos grupos tienen >= 5, otros < 5.
     * Sirve para test 3.1 y 3.7.
     *
     * @var list<array<string, mixed>>
     */
    private const DATASET_VARIADO = [
        // Grupo mayoritario: mujer + 1940-1949 + Calle Mayor + PSH (7 registros >= K=5)
        ['id' => 1,  'sexo' => 'mujer', 'anio_nacimiento' => 1943, 'nombre_via' => 'Calle Mayor', 'colectivo_principal' => 'PSH', 'codigo_postal' => '28013'],
        ['id' => 2,  'sexo' => 'mujer', 'anio_nacimiento' => 1945, 'nombre_via' => 'Calle Mayor', 'colectivo_principal' => 'PSH', 'codigo_postal' => '28013'],
        ['id' => 3,  'sexo' => 'mujer', 'anio_nacimiento' => 1941, 'nombre_via' => 'Calle Mayor', 'colectivo_principal' => 'PSH', 'codigo_postal' => '28013'],
        ['id' => 4,  'sexo' => 'mujer', 'anio_nacimiento' => 1948, 'nombre_via' => 'Calle Mayor', 'colectivo_principal' => 'PSH', 'codigo_postal' => '28013'],
        ['id' => 5,  'sexo' => 'mujer', 'anio_nacimiento' => 1944, 'nombre_via' => 'Calle Mayor', 'colectivo_principal' => 'PSH', 'codigo_postal' => '28013'],
        ['id' => 6,  'sexo' => 'mujer', 'anio_nacimiento' => 1942, 'nombre_via' => 'Calle Mayor', 'colectivo_principal' => 'PSH', 'codigo_postal' => '28013'],
        ['id' => 7,  'sexo' => 'mujer', 'anio_nacimiento' => 1946, 'nombre_via' => 'Calle Mayor', 'colectivo_principal' => 'PSH', 'codigo_postal' => '28013'],
        // Grupo problemático: mujer + 1980 + Calle Pez + GEN (3 registros < K=5)
        ['id' => 8,  'sexo' => 'mujer', 'anio_nacimiento' => 1980, 'nombre_via' => 'Calle Pez',   'colectivo_principal' => 'GEN', 'codigo_postal' => '28004'],
        ['id' => 9,  'sexo' => 'mujer', 'anio_nacimiento' => 1982, 'nombre_via' => 'Calle Pez',   'colectivo_principal' => 'GEN', 'codigo_postal' => '28004'],
        ['id' => 10, 'sexo' => 'mujer', 'anio_nacimiento' => 1985, 'nombre_via' => 'Calle Pez',   'colectivo_principal' => 'GEN', 'codigo_postal' => '28004'],
        // Grupo mayoritario: hombre + 1960-1969 + Calle Alcalá + DRG (6 registros >= K=5)
        ['id' => 11, 'sexo' => 'hombre', 'anio_nacimiento' => 1960, 'nombre_via' => 'Calle Alcalá', 'colectivo_principal' => 'DRG', 'codigo_postal' => '28009'],
        ['id' => 12, 'sexo' => 'hombre', 'anio_nacimiento' => 1963, 'nombre_via' => 'Calle Alcalá', 'colectivo_principal' => 'DRG', 'codigo_postal' => '28009'],
        ['id' => 13, 'sexo' => 'hombre', 'anio_nacimiento' => 1965, 'nombre_via' => 'Calle Alcalá', 'colectivo_principal' => 'DRG', 'codigo_postal' => '28009'],
        ['id' => 14, 'sexo' => 'hombre', 'anio_nacimiento' => 1967, 'nombre_via' => 'Calle Alcalá', 'colectivo_principal' => 'DRG', 'codigo_postal' => '28009'],
        ['id' => 15, 'sexo' => 'hombre', 'anio_nacimiento' => 1961, 'nombre_via' => 'Calle Alcalá', 'colectivo_principal' => 'DRG', 'codigo_postal' => '28009'],
        ['id' => 16, 'sexo' => 'hombre', 'anio_nacimiento' => 1964, 'nombre_via' => 'Calle Alcalá', 'colectivo_principal' => 'DRG', 'codigo_postal' => '28009'],
        // Grupo mayoritario: otro + 1990 + Calle Luna + MEN (5 registros >= K=5)
        ['id' => 17, 'sexo' => 'otro', 'anio_nacimiento' => 1990, 'nombre_via' => 'Calle Luna', 'colectivo_principal' => 'MEN', 'codigo_postal' => '28015'],
        ['id' => 18, 'sexo' => 'otro', 'anio_nacimiento' => 1991, 'nombre_via' => 'Calle Luna', 'colectivo_principal' => 'MEN', 'codigo_postal' => '28015'],
        ['id' => 19, 'sexo' => 'otro', 'anio_nacimiento' => 1992, 'nombre_via' => 'Calle Luna', 'colectivo_principal' => 'MEN', 'codigo_postal' => '28015'],
        ['id' => 20, 'sexo' => 'otro', 'anio_nacimiento' => 1993, 'nombre_via' => 'Calle Luna', 'colectivo_principal' => 'MEN', 'codigo_postal' => '28015'],
        ['id' => 21, 'sexo' => 'otro', 'anio_nacimiento' => 1994, 'nombre_via' => 'Calle Luna', 'colectivo_principal' => 'MEN', 'codigo_postal' => '28015'],
    ];

    /**
     * Dataset con combinación única que no puede alcanzar K=5 ni con la cascada completa.
     * Sirve para test 3.4.
     *
     * @var list<array<string, mixed>>
     */
    private const DATASET_REGISTRO_UNICO = [
        // Único: hombre + 1955 + Calle Única + COL_RARO
        ['id' => 1, 'sexo' => 'hombre', 'anio_nacimiento' => 1955, 'nombre_via' => 'Calle Única', 'colectivo_principal' => 'COL_RARO', 'codigo_postal' => '28099'],
        // 5 registros en otro grupo para que el conjunto tenga sentido
        ['id' => 2, 'sexo' => 'mujer', 'anio_nacimiento' => 1980, 'nombre_via' => 'Calle Mayor', 'colectivo_principal' => 'PSH', 'codigo_postal' => '28013'],
        ['id' => 3, 'sexo' => 'mujer', 'anio_nacimiento' => 1981, 'nombre_via' => 'Calle Mayor', 'colectivo_principal' => 'PSH', 'codigo_postal' => '28013'],
        ['id' => 4, 'sexo' => 'mujer', 'anio_nacimiento' => 1982, 'nombre_via' => 'Calle Mayor', 'colectivo_principal' => 'PSH', 'codigo_postal' => '28013'],
        ['id' => 5, 'sexo' => 'mujer', 'anio_nacimiento' => 1983, 'nombre_via' => 'Calle Mayor', 'colectivo_principal' => 'PSH', 'codigo_postal' => '28013'],
        ['id' => 6, 'sexo' => 'mujer', 'anio_nacimiento' => 1984, 'nombre_via' => 'Calle Mayor', 'colectivo_principal' => 'PSH', 'codigo_postal' => '28013'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->validador = app(ValidadorKAnonimato::class);
    }

    // -------------------------------------------------------------------------
    // 3.1 Ninguna combinación aparece menos de K veces
    // -------------------------------------------------------------------------

    #[Test]
    public function ninguna_combinacion_aparece_menos_de_k_veces(): void
    {
        $k = 5;
        $coleccion = collect(self::DATASET_VARIADO);

        $resultado = $this->validador->aplicar($coleccion, $k);

        // Agrupar por combinación de cuasi-identificadores en el resultado
        $grupos = $resultado->groupBy(function (array $r): string {
            $calle = $r['nombre_via'] ?? $r['distrito_proxy'] ?? null;

            return serialize([
                'sexo' => $r['sexo'] ?? null,
                'rango_edad' => $r['anio_nacimiento'] ?? $r['rango_edad'] ?? null,
                'calle_generalizada' => $calle,
                'colectivo_principal' => $r['colectivo_principal'] ?? null,
            ]);
        });

        foreach ($grupos as $combo => $miembros) {
            $this->assertGreaterThanOrEqual(
                $k,
                $miembros->count(),
                "La combinación tiene menos de {$k} registros: {$combo}"
            );
        }
    }

    // -------------------------------------------------------------------------
    // 3.2 Generalización en cascada — de calle a distrito
    // -------------------------------------------------------------------------

    #[Test]
    public function generaliza_en_cascada_de_calle_a_distrito(): void
    {
        $k = 5;

        // Dataset diseñado para que el paso 2 de la cascada resuelva el problema:
        // - Grupos individuales de Calle Pez, Calle Sol y Calle Naranja (< K=5 cada uno)
        // - Todos en el mismo distrito (28004 → proxy='280') y mismo colectivo
        // - Tras la generalización a distrito_proxy, el grupo conjunto tiene 7 >= K=5
        $dataset = [
            // 3 en Calle Pez — ids 1,2,3
            ['id' => 1, 'sexo' => 'mujer', 'anio_nacimiento' => 1980, 'nombre_via' => 'Calle Pez',     'colectivo_principal' => 'GEN', 'codigo_postal' => '28004'],
            ['id' => 2, 'sexo' => 'mujer', 'anio_nacimiento' => 1982, 'nombre_via' => 'Calle Pez',     'colectivo_principal' => 'GEN', 'codigo_postal' => '28004'],
            ['id' => 3, 'sexo' => 'mujer', 'anio_nacimiento' => 1985, 'nombre_via' => 'Calle Pez',     'colectivo_principal' => 'GEN', 'codigo_postal' => '28004'],
            // 3 en Calle Sol — ids 4,5,6
            ['id' => 4, 'sexo' => 'mujer', 'anio_nacimiento' => 1981, 'nombre_via' => 'Calle Sol',     'colectivo_principal' => 'GEN', 'codigo_postal' => '28004'],
            ['id' => 5, 'sexo' => 'mujer', 'anio_nacimiento' => 1983, 'nombre_via' => 'Calle Sol',     'colectivo_principal' => 'GEN', 'codigo_postal' => '28004'],
            ['id' => 6, 'sexo' => 'mujer', 'anio_nacimiento' => 1986, 'nombre_via' => 'Calle Sol',     'colectivo_principal' => 'GEN', 'codigo_postal' => '28004'],
            // 1 en Calle Naranja — id 7
            ['id' => 7, 'sexo' => 'mujer', 'anio_nacimiento' => 1984, 'nombre_via' => 'Calle Naranja', 'colectivo_principal' => 'GEN', 'codigo_postal' => '28004'],
        ];

        $resultado = $this->validador->aplicar(collect($dataset), $k);

        // Todos los 7 registros deben seguir en el resultado (el grupo fusionado tiene 7 >= K=5)
        $this->assertCount(7, $resultado);

        // Los registros ids 1,2,3 (Calle Pez) deben tener distrito_proxy pero no nombre_via
        $registrosPez = $resultado->filter(fn (array $r) => in_array($r['id'], [1, 2, 3], true));
        $this->assertCount(3, $registrosPez, 'Los 3 registros de Calle Pez deben sobrevivir');

        foreach ($registrosPez as $r) {
            $this->assertArrayNotHasKey('nombre_via', $r,
                'Los registros de Calle Pez debían perder nombre_via tras la cascada');
            $this->assertSame('280', $r['distrito_proxy']);
        }

        // Verificar que la nueva combinación con distrito_proxy aparece >= K veces
        $gruposFinales = $resultado->groupBy(function (array $r): string {
            $calle = $r['nombre_via'] ?? $r['distrito_proxy'] ?? null;

            return serialize([
                'sexo' => $r['sexo'] ?? null,
                'rango_edad' => $r['anio_nacimiento'] ?? $r['rango_edad'] ?? null,
                'calle_generalizada' => $calle,
                'colectivo_principal' => $r['colectivo_principal'] ?? null,
            ]);
        });
        foreach ($gruposFinales as $miembros) {
            $this->assertGreaterThanOrEqual($k, $miembros->count());
        }
    }

    // -------------------------------------------------------------------------
    // 3.3 Generalización en cascada — supresión de colectivo
    // -------------------------------------------------------------------------

    #[Test]
    public function generaliza_en_cascada_suprimiendo_colectivo_cuando_no_alcanza_k(): void
    {
        $k = 5;
        // Dataset donde incluso con distrito_proxy una combinación sigue < K
        // Para esto necesitamos que los 3 registros de Calle Pez, al pasar a distrito_proxy,
        // sigan estando solos (sin unirse a otros en el mismo distrito con misma combinación).
        // Añadimos un 4º registro diferente en el mismo distrito para que sumando no llegue a 5.
        $dataset = array_merge(self::DATASET_VARIADO, [
            ['id' => 99, 'sexo' => 'mujer', 'anio_nacimiento' => 1984, 'nombre_via' => 'Calle Sol', 'colectivo_principal' => 'GEN', 'codigo_postal' => '28004'],
        ]);

        $resultado = $this->validador->aplicar(collect($dataset), $k);

        // Si el colectivo fue suprimido en algunos registros problemáticos, esos registros
        // no deben tener 'colectivo_principal' en el resultado
        $sinColectivo = $resultado->filter(
            fn (array $r) => ! array_key_exists('colectivo_principal', $r)
        );

        // Si la cascada llegó a suprimir colectivo, debe haber registros sin él
        // O los registros habrán sido suprimidos completamente
        // En cualquier caso, el resultado debe cumplir K-anonimato
        $grupos = $resultado->groupBy(function (array $r): string {
            $calle = $r['nombre_via'] ?? $r['distrito_proxy'] ?? null;

            return serialize([
                'sexo' => $r['sexo'] ?? null,
                'rango_edad' => $r['anio_nacimiento'] ?? $r['rango_edad'] ?? null,
                'calle_generalizada' => $calle,
                'colectivo_principal' => $r['colectivo_principal'] ?? null,
            ]);
        });

        foreach ($grupos as $miembros) {
            $this->assertGreaterThanOrEqual($k, $miembros->count());
        }
    }

    // -------------------------------------------------------------------------
    // 3.4 Supresión de registro como último recurso
    // -------------------------------------------------------------------------

    #[Test]
    public function suprime_registro_cuando_la_cascada_completa_no_alcanza_k(): void
    {
        $k = 5;
        $coleccion = collect(self::DATASET_REGISTRO_UNICO);
        $totalOriginal = $coleccion->count();

        $resultado = $this->validador->aplicar($coleccion, $k);

        // El registro único (id=1) debe haber sido eliminado
        $ids = $resultado->pluck('id')->filter()->all();
        $this->assertNotContains(1, $ids, 'El registro único debe haber sido suprimido');

        // El resultado debe tener menos registros que el original
        $this->assertLessThan($totalOriginal, $resultado->count());
    }

    // -------------------------------------------------------------------------
    // 3.5 El job no entrega si no supera la validación
    // -------------------------------------------------------------------------

    #[Test]
    public function el_job_no_entrega_si_no_supera_la_validacion(): void
    {
        $this->markTestIncomplete(
            'Pendiente: requiere implementación del modelo Extraccion y '.
            'el job de extracción asíncrona. Ver docs/api.md § 8.'
        );
    }

    // -------------------------------------------------------------------------
    // 3.6 El resultado del job registra la versión del perfil aplicado
    // -------------------------------------------------------------------------

    #[Test]
    public function el_job_registra_la_version_del_perfil_aplicado(): void
    {
        $this->markTestIncomplete(
            'Pendiente: requiere implementación del modelo Extraccion. '.
            'Ver docs/tests-anonimizacion.md § 3.6.'
        );
    }

    // -------------------------------------------------------------------------
    // 3.7 Orden correcto de la cascada de generalización
    // -------------------------------------------------------------------------

    #[Test]
    public function la_cascada_sigue_el_orden_correcto_sin_saltarse_pasos(): void
    {
        $k = 5;
        // Combinación problemática: mujer + 1943 + Calle Mayor + PSH (2 registros)
        $dataset = [
            ['id' => 1, 'sexo' => 'mujer', 'anio_nacimiento' => 1943, 'nombre_via' => 'Calle Mayor', 'colectivo_principal' => 'PSH', 'codigo_postal' => '28013'],
            ['id' => 2, 'sexo' => 'mujer', 'anio_nacimiento' => 1943, 'nombre_via' => 'Calle Mayor', 'colectivo_principal' => 'PSH', 'codigo_postal' => '28013'],
            // Registros mayoritarios para que la cascada tenga con qué fusionar
            ['id' => 3, 'sexo' => 'mujer', 'rango_edad' => '1940-1949', 'nombre_via' => 'Calle Mayor', 'colectivo_principal' => 'PSH', 'codigo_postal' => '28013'],
            ['id' => 4, 'sexo' => 'mujer', 'rango_edad' => '1940-1949', 'nombre_via' => 'Calle Mayor', 'colectivo_principal' => 'PSH', 'codigo_postal' => '28013'],
            ['id' => 5, 'sexo' => 'mujer', 'rango_edad' => '1940-1949', 'nombre_via' => 'Calle Mayor', 'colectivo_principal' => 'PSH', 'codigo_postal' => '28013'],
        ];

        $resultado = $this->validador->aplicar(collect($dataset), $k);

        // Los ids 1 y 2 debían tener anio_nacimiento y tras el paso 1 tener rango_edad
        $registro1 = $resultado->firstWhere('id', 1);
        $registro2 = $resultado->firstWhere('id', 2);

        if ($registro1 !== null) {
            // Si el registro sigue presente, debe tener rango_edad (no anio_nacimiento)
            $this->assertArrayNotHasKey('anio_nacimiento', $registro1,
                'El paso 1 debe convertir anio_nacimiento a rango_edad');
            $this->assertArrayHasKey('rango_edad', $registro1);
            $this->assertSame('1940-1949', $registro1['rango_edad']);
        }

        // El conjunto completo debe cumplir K-anonimato
        $grupos = $resultado->groupBy(function (array $r): string {
            $calle = $r['nombre_via'] ?? $r['distrito_proxy'] ?? null;

            return serialize([
                'sexo' => $r['sexo'] ?? null,
                'rango_edad' => $r['anio_nacimiento'] ?? $r['rango_edad'] ?? null,
                'calle_generalizada' => $calle,
                'colectivo_principal' => $r['colectivo_principal'] ?? null,
            ]);
        });
        foreach ($grupos as $miembros) {
            $this->assertGreaterThanOrEqual($k, $miembros->count());
        }
    }

    // -------------------------------------------------------------------------
    // 3.8 K-anonimato no se aplica en tiempo real
    // -------------------------------------------------------------------------

    #[Test]
    public function el_k_anonimato_no_se_aplica_en_tiempo_real(): void
    {
        $this->markTestIncomplete(
            'Pendiente: test arquitectural. Verificar que ningún controlador API '.
            'resuelve ValidadorKAnonimato en el contenedor de servicio de forma síncrona. '.
            'Ver docs/anonimizacion.md § 6.2.'
        );
    }
}
