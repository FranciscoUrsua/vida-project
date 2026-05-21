<?php

namespace Tests\Feature;

use App\Enums\OrigenDireccion;
use App\Enums\TipoNumeracion;
use App\Models\Ciudadano;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'nombre'           => 'Juan',
            'apellido1'        => 'García',
            'fecha_nacimiento' => '1990-01-01',
            'sexo'             => 'hombre',
            'direccion_texto'  => 'C/ Mayor 15, Madrid 28013',
            'origen_direccion' => OrigenDireccion::Profesional,
            'activo'           => true,
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
            'nombre'           => 'Ana',
            'apellido1'        => 'Martínez',
            'fecha_nacimiento' => '1985-05-20',
            'sexo'             => 'mujer',
            'direccion_texto'  => 'Avenida Castellana 100',
            'origen_direccion' => OrigenDireccion::Profesional,
            'activo'           => true,
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
            'nombre'            => 'María',
            'apellido1'         => 'López',
            'fecha_nacimiento'  => '1975-03-12',
            'sexo'              => 'mujer',
            'direccion_texto'   => 'C/ Alcalá 50',
            'origen_direccion'  => OrigenDireccion::Padron,
            // Campos pre-rellenados por el padrón — no deben ser sobrescritos
            'tipo_via'          => 'Calle',
            'nombre_via'        => 'Alcalá',
            'numero'            => '50',
            'tipo_numeracion'   => TipoNumeracion::Numero,
            'coordenadas_lat'   => 40.4200000,
            'coordenadas_lng'   => -3.7000000,
            'direccion_normalizada' => true,
            'activo'            => true,
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
            'nombre'           => 'Pedro',
            'apellido1'        => 'Sánchez',
            'fecha_nacimiento' => '2000-08-30',
            'sexo'             => 'hombre',
            'activo'           => true,
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
            'nombre'           => 'Carlos',
            'apellido1'        => 'Ruiz',
            'fecha_nacimiento' => '1988-11-11',
            'sexo'             => 'hombre',
            'activo'           => true,
        ]);

        $this->assertFalse($ciudadano->direccion_normalizada);

        $ciudadano->update([
            'direccion_texto'  => 'Plaza Mayor 1, 28012 Madrid',
            'origen_direccion' => OrigenDireccion::Profesional,
        ]);

        $ciudadano->refresh();

        $this->assertTrue($ciudadano->direccion_normalizada);
        $this->assertNotNull($ciudadano->tipo_via);
        $this->assertEquals('mock', $ciudadano->geocoder_proveedor);
    }
}
