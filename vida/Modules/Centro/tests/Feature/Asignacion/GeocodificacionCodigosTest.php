<?php

namespace Modules\Centro\Tests\Feature\Asignacion;

use App\Enums\OrigenDireccion;
use App\Models\Ciudadano;
use App\Services\Geocodificacion\GeocodificadorInterface;
use App\Services\Geocodificacion\ResultadoGeocodificacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Organizacion\Models\Barrio;
use Modules\Organizacion\Models\Distrito;
use Modules\Organizacion\Models\SeccionCensal;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo A: códigos territoriales de la dirección (TF-ASG-03, TF-ASG-04).
 *
 * Usan el catálogo real cargado por la migración: el mock elige una sección de él.
 *
 * @see docs/instrucciones-cli/2026-09-asignacion-tests.md
 */
class GeocodificacionCodigosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Evita que el job de reintento se ejecute de forma síncrona en los tests.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    /**
     * Crea un ciudadano con dirección introducida por un profesional.
     *
     * @param string $direccion Texto libre de la dirección.
     * @return Ciudadano
     */
    private function ciudadanoConDireccion(string $direccion): Ciudadano
    {
        return Ciudadano::factory()->create([
            'direccion_texto' => $direccion,
            'origen_direccion' => OrigenDireccion::Profesional,
        ]);
    }

    /**
     * TF-ASG-03 — La dirección normalizada guarda NDP, distrito, barrio y sección coherentes.
     *
     * @return void
     */
    #[Test]
    public function la_direccion_normalizada_guarda_codigos_coherentes_con_el_catalogo(): void
    {
        $ana = $this->ciudadanoConDireccion('Calle de Alcalá 120, 3º B, 28009 Madrid');

        $this->assertTrue($ana->direccion_normalizada);
        $this->assertNotNull($ana->codigo_ndp);

        $seccion = SeccionCensal::where('codigo_ine', $ana->seccion_censal_codigo)->first();
        $this->assertNotNull($seccion, 'La sección debe existir en el catálogo');
        $this->assertSame($seccion->barrio->codigo, $ana->barrio_codigo);
        $this->assertSame($seccion->distrito->codigo, $ana->distrito_codigo);
        $this->assertSame(Barrio::where('codigo', $ana->barrio_codigo)->value('distrito_id'), Distrito::where('codigo', $ana->distrito_codigo)->value('id'));

        // La misma dirección normalizada otra vez da la misma sección y el mismo portal
        $otra = $this->ciudadanoConDireccion('Calle de Alcalá 120, 3º B, 28009 Madrid');
        $this->assertSame($ana->seccion_censal_codigo, $otra->seccion_censal_codigo);
        $this->assertSame($ana->codigo_ndp, $otra->codigo_ndp);

        // Otro piso del mismo portal comparte NDP y sección
        $vecino = $this->ciudadanoConDireccion('Calle de Alcalá 120, 1º A, 28009 Madrid');
        $this->assertSame($ana->codigo_ndp, $vecino->codigo_ndp);
        $this->assertSame($ana->seccion_censal_codigo, $vecino->seccion_censal_codigo);
    }

    /**
     * TF-ASG-04 — Un fallo de geocodificación deja los cuatro códigos a null.
     *
     * @return void
     */
    #[Test]
    public function un_fallo_de_geocodificacion_deja_los_codigos_nulos(): void
    {
        $ana = $this->ciudadanoConDireccion('Calle de Alcalá 120, 28009 Madrid');
        $this->assertNotNull($ana->seccion_censal_codigo);

        $this->app->instance(GeocodificadorInterface::class, new class implements GeocodificadorInterface
        {
            public function normalizar(string $direccionTexto): ResultadoGeocodificacion
            {
                return ResultadoGeocodificacion::fallo('prueba', 'Sin respuesta');
            }
        });

        $ana->update(['direccion_texto' => 'Calle Inventada 1']);
        $ana->refresh();

        $this->assertFalse($ana->direccion_normalizada);
        $this->assertNull($ana->codigo_ndp);
        $this->assertNull($ana->distrito_codigo);
        $this->assertNull($ana->barrio_codigo);
        $this->assertNull($ana->seccion_censal_codigo);
    }
}
