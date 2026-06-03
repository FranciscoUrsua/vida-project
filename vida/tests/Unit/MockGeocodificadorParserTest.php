<?php

namespace Tests\Unit;

use App\Enums\TipoNumeracion;
use App\Services\Geocodificacion\Adaptadores\MockGeocodificador;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios del parser de texto libre del MockGeocodificador.
 *
 * Verifican que el parser extrae correctamente los campos estructurados
 * de una dirección española sin depender de la base de datos ni del
 * contenedor de Laravel.
 */
class MockGeocodificadorParserTest extends TestCase
{
    private MockGeocodificador $geocodificador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->geocodificador = new MockGeocodificador;
    }

    // -------------------------------------------------------------------------
    // Tipo de vía abreviado
    // -------------------------------------------------------------------------

    #[Test]
    public function parsea_tipo_de_via_abreviado_c_barra(): void
    {
        $resultado = $this->geocodificador->normalizar('C/ Mayor 15, Madrid 28013');

        $this->assertTrue($resultado->exito);
        $this->assertEquals('Calle', $resultado->tipoVia);
        $this->assertEquals('Mayor', $resultado->nombreVia);
        $this->assertEquals('15', $resultado->numero);
        $this->assertEquals(TipoNumeracion::Numero, $resultado->tipoNumeracion);
        $this->assertEquals('28013', $resultado->codigoPostal);
    }

    #[Test]
    public function parsea_tipo_de_via_abreviado_avda(): void
    {
        $resultado = $this->geocodificador->normalizar('Avda. Constitución 7 3º izq');

        $this->assertTrue($resultado->exito);
        $this->assertEquals('Avenida', $resultado->tipoVia);
        $this->assertStringContainsString('Constitución', (string) $resultado->nombreVia);
        $this->assertEquals('7', $resultado->numero);
    }

    #[Test]
    public function parsea_tipo_de_via_pza(): void
    {
        $resultado = $this->geocodificador->normalizar('Pza. España 1');

        $this->assertTrue($resultado->exito);
        $this->assertEquals('Plaza', $resultado->tipoVia);
    }

    // -------------------------------------------------------------------------
    // Dirección sin número
    // -------------------------------------------------------------------------

    #[Test]
    public function parsea_direccion_con_sin_numero_abreviado(): void
    {
        $resultado = $this->geocodificador->normalizar('Calle Alcalá s/n, Madrid');

        $this->assertTrue($resultado->exito);
        $this->assertEquals('Calle', $resultado->tipoVia);
        $this->assertEquals(TipoNumeracion::SinNumero, $resultado->tipoNumeracion);
        $this->assertNull($resultado->numero);
    }

    #[Test]
    public function parsea_direccion_con_sin_numero_escrito(): void
    {
        $resultado = $this->geocodificador->normalizar('Paseo Castellana sin número');

        $this->assertTrue($resultado->exito);
        $this->assertEquals(TipoNumeracion::SinNumero, $resultado->tipoNumeracion);
        $this->assertNull($resultado->numero);
    }

    // -------------------------------------------------------------------------
    // Dirección con piso y puerta
    // -------------------------------------------------------------------------

    #[Test]
    public function parsea_piso_con_ordinal(): void
    {
        $resultado = $this->geocodificador->normalizar('Calle Gran Vía 28, 4º izq');

        $this->assertTrue($resultado->exito);
        $this->assertEquals('28', $resultado->numero);
        $this->assertEquals('4', $resultado->piso);
        $this->assertEquals('izq', $resultado->puerta);
    }

    #[Test]
    public function parsea_puerta_derecha(): void
    {
        $resultado = $this->geocodificador->normalizar('C/ Serrano 10, 2º dcha');

        $this->assertTrue($resultado->exito);
        $this->assertEquals('2', $resultado->piso);
        $this->assertStringStartsWith('dcha', (string) $resultado->puerta);
    }

    // -------------------------------------------------------------------------
    // Texto sin estructura reconocible
    // -------------------------------------------------------------------------

    #[Test]
    public function texto_sin_estructura_devuelve_exito_sin_numero(): void
    {
        $resultado = $this->geocodificador->normalizar('junto al mercado de abastos');

        $this->assertTrue($resultado->exito);
        // Sin dígitos → no hay número extraíble
        $this->assertNull($resultado->numero);
        $this->assertNotEquals(TipoNumeracion::Numero, $resultado->tipoNumeracion);
    }

    #[Test]
    public function texto_sin_estructura_devuelve_coordenadas_en_bbox_de_madrid(): void
    {
        $resultado = $this->geocodificador->normalizar('en algún lugar de la ciudad');

        $this->assertTrue($resultado->exito);
        $this->assertNotNull($resultado->latitud);
        $this->assertNotNull($resultado->longitud);
        $this->assertGreaterThanOrEqual(40.31, $resultado->latitud);
        $this->assertLessThanOrEqual(40.53, $resultado->latitud);
        $this->assertGreaterThanOrEqual(-3.83, $resultado->longitud);
        $this->assertLessThanOrEqual(-3.52, $resultado->longitud);
    }

    // -------------------------------------------------------------------------
    // Código postal
    // -------------------------------------------------------------------------

    #[Test]
    public function extrae_codigo_postal_de_madrid(): void
    {
        $resultado = $this->geocodificador->normalizar('Calle Toledo 45, 28005 Madrid');

        $this->assertEquals('28005', $resultado->codigoPostal);
        $this->assertEquals('Madrid', $resultado->municipio);
    }

    #[Test]
    public function no_extrae_codigo_postal_si_no_empieza_por_28(): void
    {
        $resultado = $this->geocodificador->normalizar('Calle Mayor 3, 08001 Barcelona');

        $this->assertNull($resultado->codigoPostal);
    }

    // -------------------------------------------------------------------------
    // Identificador de proveedor
    // -------------------------------------------------------------------------

    #[Test]
    public function el_proveedor_es_mock(): void
    {
        $resultado = $this->geocodificador->normalizar('C/ Goya 22');

        $this->assertEquals('mock', $resultado->proveedor);
    }
}
