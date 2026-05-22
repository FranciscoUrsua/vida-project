<?php

namespace Tests\Feature\Anonimizacion;

use App\Exceptions\Anonimizacion\PerfilAnonimizacionInactivoException;
use App\Exceptions\Anonimizacion\PerfilAnonimizacionNotFoundException;
use App\Models\Ciudadano;
use App\Services\Api\AnonimizadorService;
use Database\Factories\PerfilAnonimizacionFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo 4 — AnonimizadorService (contrato del servicio).
 * Ver docs/tests-anonimizacion.md § Grupo 4.
 */
class AnonimizadorServiceTest extends TestCase
{
    use RefreshDatabase;

    private AnonimizadorService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = app(AnonimizadorService::class);
    }

    // -------------------------------------------------------------------------
    // 4.1 El servicio acepta una colección y un perfil y devuelve una colección
    // -------------------------------------------------------------------------

    #[Test]
    public function acepta_coleccion_y_devuelve_coleccion_de_arrays(): void
    {
        PerfilAnonimizacionFactory::new()->analiticaInterna()->create();
        $n         = 5;
        $registros = collect(array_map(
            fn (int $i) => ['id' => $i, 'nombre' => "Persona {$i}", 'apellido1' => 'Test', 'fecha_nacimiento' => '1980-01-01', 'sexo' => 'mujer'],
            range(1, $n)
        ));

        $resultado = $this->servicio->anonimizar($registros, 'analitica_interna');

        $this->assertInstanceOf(Collection::class, $resultado);
        $this->assertCount($n, $resultado);

        foreach ($resultado as $item) {
            $this->assertIsArray($item, 'Cada elemento del resultado debe ser un array, no un modelo Eloquent');
        }
    }

    // -------------------------------------------------------------------------
    // 4.2 El servicio es transparente para el código consumidor
    // -------------------------------------------------------------------------

    #[Test]
    public function el_servicio_es_transparente_para_el_codigo_consumidor(): void
    {
        PerfilAnonimizacionFactory::new()->supervisionInterna()->create();

        // El consumidor no necesita saber qué campos se transforman
        $registros = collect([['id' => 1, 'nombre' => 'Test', 'fecha_nacimiento' => '1980-01-01']]);

        $resultado = $this->servicio->anonimizar($registros, 'supervision_interna');

        $this->assertInstanceOf(Collection::class, $resultado);
        $this->assertCount(1, $resultado);
    }

    // -------------------------------------------------------------------------
    // 4.3 Perfil inexistente lanza excepción
    // -------------------------------------------------------------------------

    #[Test]
    public function perfil_inexistente_lanza_excepcion(): void
    {
        $this->expectException(PerfilAnonimizacionNotFoundException::class);

        $this->servicio->anonimizar(collect([['id' => 1]]), 'perfil_que_no_existe');
    }

    // -------------------------------------------------------------------------
    // 4.4 Perfil inactivo lanza excepción
    // -------------------------------------------------------------------------

    #[Test]
    public function perfil_inactivo_lanza_excepcion(): void
    {
        PerfilAnonimizacionFactory::new()->inactivo()->create(['nombre' => 'perfil_inactivo']);

        $this->expectException(PerfilAnonimizacionInactivoException::class);

        $this->servicio->anonimizar(collect([['id' => 1]]), 'perfil_inactivo');
    }

    // -------------------------------------------------------------------------
    // 4.5 La capa de transformación actúa después del descifrado
    // -------------------------------------------------------------------------

    #[Test]
    public function la_transformacion_actua_despues_del_descifrado(): void
    {
        PerfilAnonimizacionFactory::new()->supervisionInterna()->create();

        // El modelo Ciudadano tiene el campo 'nombre' con cast 'encrypted'.
        // Al llamar ->toArray(), Eloquent devuelve el valor descifrado.
        // El servicio suprime ese valor — nunca expone el texto cifrado en bruto.
        $ciudadano = Ciudadano::factory()->create(['nombre' => 'María Descifrada']);
        $coleccion = collect([$ciudadano]);

        $resultado = $this->servicio->anonimizar($coleccion, 'supervision_interna')->first();

        $this->assertArrayNotHasKey('nombre', $resultado,
            'El campo nombre debe estar suprimido tras la anonimización');
        // El alias seudonimizado sí debe estar presente
        $this->assertArrayHasKey('alias_ciudadano', $resultado);
    }

    // -------------------------------------------------------------------------
    // 4.6 Colección vacía devuelve colección vacía sin error
    // -------------------------------------------------------------------------

    #[Test]
    public function coleccion_vacia_devuelve_coleccion_vacia_sin_error(): void
    {
        PerfilAnonimizacionFactory::new()->analiticaInterna()->create();

        $resultado = $this->servicio->anonimizar(collect([]), 'analitica_interna');

        $this->assertInstanceOf(Collection::class, $resultado);
        $this->assertCount(0, $resultado);
    }

    // -------------------------------------------------------------------------
    // Verificación de que el servicio no importa clases de Modules\
    // -------------------------------------------------------------------------

    #[Test]
    public function el_servicio_no_tiene_dependencias_con_modulos_funcionales(): void
    {
        $contenido = file_get_contents(
            base_path('app/Services/Api/AnonimizadorService.php')
        );

        $this->assertStringNotContainsString(
            'Modules\\',
            $contenido,
            'AnonimizadorService no debe importar clases de módulos funcionales'
        );
    }
}
