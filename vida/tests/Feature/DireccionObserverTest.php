<?php

namespace Tests\Feature;

use App\Enums\OrigenDireccion;
use App\Enums\TipoNumeracion;
use App\Jobs\NormalizarDireccionJob;
use App\Models\Ciudadano;
use App\Services\Geocodificacion\Adaptadores\SinGeocodificador;
use App\Services\Geocodificacion\GeocodificadorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Modules\Organizacion\Models\Configuracion;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de integración del DireccionObserver.
 *
 * Verifican que al guardar un Ciudadano con dirección manual se invocan los
 * campos estructurados, y que las direcciones procedentes del padrón no
 * pasan por el geocoder.
 *
 * Usa el MockGeocodificador (proveedor por defecto) para no depender de
 * servicios externos.
 */
class DireccionObserverTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Caso 1: dirección manual → debe geocodificar
    // -------------------------------------------------------------------------

    #[Test]
    public function guardar_ciudadano_con_origen_profesional_rellena_campos_estructurados(): void
    {
        $ciudadano = Ciudadano::create([
            'nombre' => 'Juan',
            'apellido1' => 'García',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'hombre',
            'direccion_texto' => 'C/ Mayor 15, Madrid 28013',
            'origen_direccion' => OrigenDireccion::Profesional,
            'activo' => true,
        ]);

        $this->assertTrue($ciudadano->direccion_normalizada);
        $this->assertEquals('Calle', $ciudadano->tipo_via);
        $this->assertEquals('mock', $ciudadano->geocoder_proveedor);
        $this->assertNotNull($ciudadano->coordenadas_lat);
        $this->assertNotNull($ciudadano->coordenadas_lng);

        // Coordenadas dentro del bbox de Madrid
        $this->assertGreaterThanOrEqual(40.31, $ciudadano->coordenadas_lat);
        $this->assertLessThanOrEqual(40.53, $ciudadano->coordenadas_lat);
        $this->assertGreaterThanOrEqual(-3.83, $ciudadano->coordenadas_lng);
        $this->assertLessThanOrEqual(-3.52, $ciudadano->coordenadas_lng);
    }

    #[Test]
    public function el_tipo_numeracion_se_establece_correctamente_para_numero_normal(): void
    {
        $ciudadano = Ciudadano::create([
            'nombre' => 'Ana',
            'apellido1' => 'Martínez',
            'fecha_nacimiento' => '1985-05-20',
            'sexo' => 'mujer',
            'direccion_texto' => 'Avenida Castellana 100',
            'origen_direccion' => OrigenDireccion::Profesional,
            'activo' => true,
        ]);

        $this->assertTrue($ciudadano->direccion_normalizada);
        $this->assertEquals(TipoNumeracion::Numero, $ciudadano->tipo_numeracion);
        $this->assertEquals('100', $ciudadano->numero);
    }

    // -------------------------------------------------------------------------
    // Caso 2: dirección del padrón → NO debe geocodificar
    // -------------------------------------------------------------------------

    #[Test]
    public function guardar_ciudadano_con_origen_padron_no_invoca_el_geocoder(): void
    {
        $ciudadano = Ciudadano::create([
            'nombre' => 'María',
            'apellido1' => 'López',
            'fecha_nacimiento' => '1975-03-12',
            'sexo' => 'mujer',
            'direccion_texto' => 'C/ Alcalá 50',
            'origen_direccion' => OrigenDireccion::Padron,
            // Campos pre-rellenados por el padrón — no deben ser sobrescritos
            'tipo_via' => 'Calle',
            'nombre_via' => 'Alcalá',
            'numero' => '50',
            'tipo_numeracion' => TipoNumeracion::Numero,
            'coordenadas_lat' => 40.4200000,
            'coordenadas_lng' => -3.7000000,
            'direccion_normalizada' => true,
            'activo' => true,
        ]);

        // Las coordenadas exactas del padrón se conservan sin modificar
        $this->assertEquals(40.4200000, $ciudadano->coordenadas_lat);
        $this->assertEquals(-3.7000000, $ciudadano->coordenadas_lng);
        $this->assertEquals('Alcalá', $ciudadano->nombre_via);
        $this->assertTrue($ciudadano->direccion_normalizada);
        // El proveedor no se establece (padrón, no geocoder)
        $this->assertNull($ciudadano->geocoder_proveedor);
    }

    // -------------------------------------------------------------------------
    // Caso 3: sin dirección → no se produce error
    // -------------------------------------------------------------------------

    #[Test]
    public function guardar_ciudadano_sin_direccion_no_produce_error(): void
    {
        $ciudadano = Ciudadano::create([
            'nombre' => 'Pedro',
            'apellido1' => 'Sánchez',
            'fecha_nacimiento' => '2000-08-30',
            'sexo' => 'hombre',
            'activo' => true,
        ]);

        $this->assertFalse($ciudadano->direccion_normalizada);
        $this->assertNull($ciudadano->coordenadas_lat);
    }

    // -------------------------------------------------------------------------
    // Caso 4: actualizar dirección renueva la normalización
    // -------------------------------------------------------------------------

    #[Test]
    public function actualizar_direccion_texto_re_geocodifica(): void
    {
        $ciudadano = Ciudadano::create([
            'nombre' => 'Carlos',
            'apellido1' => 'Ruiz',
            'fecha_nacimiento' => '1988-11-11',
            'sexo' => 'hombre',
            'activo' => true,
        ]);

        $this->assertFalse($ciudadano->direccion_normalizada);

        $ciudadano->update([
            'direccion_texto' => 'Plaza Mayor 1, 28012 Madrid',
            'origen_direccion' => OrigenDireccion::Profesional,
        ]);

        $ciudadano->refresh();

        $this->assertTrue($ciudadano->direccion_normalizada);
        $this->assertNotNull($ciudadano->tipo_via);
        $this->assertEquals('mock', $ciudadano->geocoder_proveedor);
    }

    // -------------------------------------------------------------------------
    // Simulador solo en tests (25 nov 2026)
    // -------------------------------------------------------------------------

    /**
     * Fuera de los tests no se usa el simulador, aunque esté configurado: la dirección
     * queda sin normalizar, sin coordenadas ni sección censal inventadas, y no se encola
     * ningún reintento. [negativo]
     */
    #[Test]
    public function fuera_de_los_tests_el_simulador_no_inventa_datos(): void
    {
        Queue::fake();
        app()->detectEnvironment(fn () => 'staging');
        Configuracion::create(['clave' => 'geocoder.proveedor', 'valor' => 'mock', 'tipo' => 'texto']);

        try {
            $this->assertSame(SinGeocodificador::IDENTIFICADOR, GeocodificadorService::proveedorActivo());
            $this->assertFalse(GeocodificadorService::hayGeocodificador());

            $ciudadano = Ciudadano::create([
                'nombre' => 'Lucía',
                'apellido1' => 'Prueba',
                'fecha_nacimiento' => '1990-01-01',
                'sexo' => 'mujer',
                'direccion_texto' => 'C/ Mayor 15, Madrid 28013',
                'origen_direccion' => OrigenDireccion::Profesional,
                'activo' => true,
            ]);
        } finally {
            app()->detectEnvironment(fn () => 'testing');
        }

        $this->assertFalse($ciudadano->direccion_normalizada);
        $this->assertNull($ciudadano->seccion_censal_codigo);
        $this->assertNull($ciudadano->coordenadas_lat);
        $this->assertSame('C/ Mayor 15, Madrid 28013', $ciudadano->direccion_texto);
        Queue::assertNotPushed(NormalizarDireccionJob::class);
    }

    /** La migración del 25 nov deja sin normalizar lo que normalizó el simulador y conserva el texto. */
    #[Test]
    public function la_migracion_retira_las_direcciones_del_simulador(): void
    {
        $ciudadano = Ciudadano::create([
            'nombre' => 'Pedro',
            'apellido1' => 'Prueba',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'hombre',
            'direccion_texto' => 'Avda. de la Albufera 120, 28038 Madrid',
            'origen_direccion' => OrigenDireccion::Profesional,
            'activo' => true,
        ]);
        $this->assertSame('mock', $ciudadano->geocoder_proveedor);

        (require database_path('migrations/2026_11_25_100002_retira_direcciones_normalizadas_por_el_simulador.php'))->up();

        $fila = DB::table('ciudadanos')->where('id', $ciudadano->id)->first();
        $this->assertFalse((bool) $fila->direccion_normalizada);
        $this->assertNull($fila->geocoder_proveedor);
        $this->assertNull($fila->coordenadas_lat);
        $this->assertNull($fila->seccion_censal_codigo);
        $this->assertSame('Avda. de la Albufera 120, 28038 Madrid', Ciudadano::withoutGlobalScopes()->find($ciudadano->id)->direccion_texto);
    }
}
