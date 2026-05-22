<?php

namespace Tests\Feature\Anonimizacion;

use App\Models\Ciudadano;
use App\Services\Api\AnonimizadorService;
use Database\Factories\PerfilAnonimizacionFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo 2 — Generalización (Nivel 2).
 * Ver docs/tests-anonimizacion.md § Grupo 2.
 */
class GeneralizacionTest extends TestCase
{
    use RefreshDatabase;

    private AnonimizadorService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = app(AnonimizadorService::class);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function registroSimple(array $campos = []): Collection
    {
        return collect([array_merge(['id' => 1], $campos)]);
    }

    // -------------------------------------------------------------------------
    // 2.1 Fecha de nacimiento — precisión "anio"
    // -------------------------------------------------------------------------

    #[Test]
    public function generaliza_fecha_nacimiento_a_anio(): void
    {
        PerfilAnonimizacionFactory::new()->analiticaInterna()->create();
        $registro = $this->registroSimple([
            'nombre'           => 'Ana',
            'apellido1'        => 'Pérez',
            'fecha_nacimiento' => '1943-07-15',
            'sexo'             => 'mujer',
        ]);

        $resultado = $this->servicio->anonimizar($registro, 'analitica_interna')->first();

        $this->assertSame(1943, $resultado['anio_nacimiento']);
        $this->assertArrayNotHasKey('fecha_nacimiento', $resultado);
    }

    // -------------------------------------------------------------------------
    // 2.2 Fecha de nacimiento — precisión "decada"
    // -------------------------------------------------------------------------

    #[Test]
    public function generaliza_fecha_nacimiento_a_decada(): void
    {
        \App\Models\Api\PerfilAnonimizacion::create([
            'nombre'     => 'perfil_decada_test',
            'nivel'      => 2,
            'version'    => 1,
            'estado'     => 'activo',
            'es_sistema' => false,
            'campos'     => [
                ['campo' => 'fecha_nacimiento', 'tecnica' => 'generalizar', 'precision' => 'decada'],
            ],
            'k_valor' => null,
        ]);

        $registro  = $this->registroSimple(['fecha_nacimiento' => '1943-07-15']);
        $resultado = $this->servicio->anonimizar($registro, 'perfil_decada_test')->first();

        $this->assertSame('1940-1949', $resultado['rango_edad']);
        $this->assertArrayNotHasKey('fecha_nacimiento', $resultado);
    }

    // -------------------------------------------------------------------------
    // 2.3 Dirección — precisión "calle_sin_numero" con dirección normalizada
    // -------------------------------------------------------------------------

    #[Test]
    public function generaliza_direccion_a_calle_sin_numero_cuando_normalizada(): void
    {
        PerfilAnonimizacionFactory::new()->analiticaInterna()->create();
        $registro = $this->registroSimple([
            'nombre'                => 'Ana',
            'apellido1'             => 'Pérez',
            'tipo_via'              => 'Calle',
            'nombre_via'            => 'Gran Vía',
            'numero'                => '28',
            'piso'                  => '3',
            'puerta'                => 'izq',
            'portal'                => null,
            'escalera'              => null,
            'direccion_normalizada' => true,
            'fecha_nacimiento'      => '1980-01-01',
            'sexo'                  => 'mujer',
        ]);

        $resultado = $this->servicio->anonimizar($registro, 'analitica_interna')->first();

        $this->assertSame('Gran Vía', $resultado['nombre_via']);
        $this->assertSame('Calle', $resultado['tipo_via']);
        $this->assertArrayNotHasKey('numero', $resultado);
        $this->assertArrayNotHasKey('piso', $resultado);
        $this->assertArrayNotHasKey('puerta', $resultado);
        $this->assertArrayNotHasKey('portal', $resultado);
        $this->assertArrayNotHasKey('escalera', $resultado);
    }

    // -------------------------------------------------------------------------
    // 2.4 Dirección — supresión completa si no está normalizada
    // -------------------------------------------------------------------------

    #[Test]
    public function suprime_nombre_via_si_direccion_no_normalizada(): void
    {
        PerfilAnonimizacionFactory::new()->analiticaInterna()->create();
        $registro = $this->registroSimple([
            'nombre'                => 'Ana',
            'apellido1'             => 'Pérez',
            'nombre_via'            => 'Calle Mayor',
            'codigo_postal'         => '28013',
            'direccion_normalizada' => false,
            'fecha_nacimiento'      => '1980-01-01',
            'sexo'                  => 'mujer',
        ]);

        $resultado = $this->servicio->anonimizar($registro, 'analitica_interna')->first();

        $this->assertArrayNotHasKey('nombre_via', $resultado);
    }

    // -------------------------------------------------------------------------
    // 2.5 Código postal — generalización a distrito (3 primeros dígitos)
    // -------------------------------------------------------------------------

    #[Test]
    public function generaliza_codigo_postal_a_distrito_proxy(): void
    {
        PerfilAnonimizacionFactory::new()->analiticaInterna()->create();
        $registro = $this->registroSimple([
            'nombre'           => 'Ana',
            'apellido1'        => 'Pérez',
            'codigo_postal'    => '28013',
            'fecha_nacimiento' => '1980-01-01',
            'sexo'             => 'mujer',
        ]);

        $resultado = $this->servicio->anonimizar($registro, 'analitica_interna')->first();

        $this->assertSame('280', $resultado['distrito_proxy']);
        $this->assertArrayNotHasKey('codigo_postal', $resultado);
    }

    // -------------------------------------------------------------------------
    // 2.6 Identificadores directos suprimidos en Nivel 2
    // -------------------------------------------------------------------------

    #[Test]
    public function identificadores_directos_suprimidos_en_nivel_2(): void
    {
        PerfilAnonimizacionFactory::new()->analiticaInterna()->create();
        $registro = $this->registroSimple([
            'nombre'              => 'Ana',
            'apellido1'           => 'Pérez',
            'apellido2'           => 'García',
            'documento_identidad' => '12345678A',
            'telefono'            => '600000000',
            'email'               => 'ana@example.com',
            'fecha_nacimiento'    => '1980-01-01',
            'sexo'                => 'mujer',
        ]);

        $resultado = $this->servicio->anonimizar($registro, 'analitica_interna')->first();

        $this->assertArrayNotHasKey('nombre', $resultado);
        $this->assertArrayNotHasKey('apellido1', $resultado);
        $this->assertArrayNotHasKey('apellido2', $resultado);
        $this->assertArrayNotHasKey('documento_identidad', $resultado);
        $this->assertArrayNotHasKey('telefono', $resultado);
        $this->assertArrayNotHasKey('email', $resultado);
        // Nivel 2 no genera alias — a diferencia del Nivel 1
        $this->assertArrayNotHasKey('alias_ciudadano', $resultado);
    }

    // -------------------------------------------------------------------------
    // 2.7 El perfil "analitica_interna" es irreversible
    // -------------------------------------------------------------------------

    #[Test]
    public function el_perfil_analitica_interna_es_irreversible(): void
    {
        PerfilAnonimizacionFactory::new()->analiticaInterna()->create();
        $registro = $this->registroSimple([
            'nombre'           => 'Ana',
            'apellido1'        => 'Pérez',
            'fecha_nacimiento' => '1980-01-01',
            'sexo'             => 'mujer',
        ]);

        $resultado = $this->servicio->anonimizar($registro, 'analitica_interna')->first();

        // Sin alias_ciudadano: no hay ningún mecanismo de reversión desde el resultado.
        // El id permanece en el array porque analitica_interna no lo suprime —
        // lo que lo hace irreversible es la ausencia del alias seudonimizado.
        $this->assertArrayNotHasKey('alias_ciudadano', $resultado);
    }
}
