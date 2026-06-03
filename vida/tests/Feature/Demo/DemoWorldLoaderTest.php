<?php

namespace Tests\Feature\Demo;

use Database\Seeders\Demo\DemoWorldLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales del sistema de world-building para entornos de demo.
 *
 * TF-DEMO-01 a TF-DEMO-07: tests del loader y comandos CLI.
 * TF-DEMO-08 a TF-DEMO-12: tests de integración pesados (pendientes).
 */
class DemoWorldLoaderTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // TF-DEMO-01: loader carga ci_minimo sin errores
    // -------------------------------------------------------------------------

    /**
     * TF-DEMO-01: El loader carga el mundo ci_minimo sin lanzar excepciones
     * y devuelve la estructura esperada.
     */
    #[Test]
    public function tf_demo_01_loader_carga_ci_minimo_sin_errores(): void
    {
        $loader = new DemoWorldLoader();
        $config = $loader->load('ci_minimo');

        $this->assertArrayHasKey('meta', $config);
        $this->assertArrayHasKey('centros', $config);
        $this->assertArrayHasKey('profesionales', $config);
        $this->assertArrayHasKey('escenarios', $config);

        $this->assertNotEmpty($config['meta']['nombre']);
        $this->assertNotEmpty($config['meta']['descripcion']);
        $this->assertNotEmpty($config['centros']);
        $this->assertNotEmpty($config['profesionales']);
    }

    // -------------------------------------------------------------------------
    // TF-DEMO-02: loader rechaza centro inexistente en profesionales
    // -------------------------------------------------------------------------

    /**
     * TF-DEMO-02: El loader rechaza un YAML donde un profesional referencia
     * un centro que no existe en la sección 'centros'.
     */
    #[Test]
    public function tf_demo_02_loader_rechaza_centro_inexistente_en_profesionales(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/centro.*no existe/');

        $loader = new DemoWorldLoader(sys_get_temp_dir());

        // Crear un YAML temporal con un profesional que referencia un centro inexistente
        $yaml = <<<YAML
meta:
  nombre: "Test"
  descripcion: "Test de centro inexistente"

centros:
  - id: c1
    nombre: CSS Test
    tipo: asp
    distrito: Centro

profesionales:
  - login: tsr1@test.es
    nombre: TSR Test
    rol: intervencion
    centro: c99_inexistente

escenarios: []
YAML;

        $tempFile = sys_get_temp_dir() . '/centro_inexistente.yaml';
        file_put_contents($tempFile, $yaml);

        try {
            $loader->load('centro_inexistente');
        } finally {
            @unlink($tempFile);
        }
    }

    // -------------------------------------------------------------------------
    // TF-DEMO-03: loader rechaza consulta_basica en escenarios
    // -------------------------------------------------------------------------

    /**
     * TF-DEMO-03: El loader rechaza un YAML donde un profesional con rol
     * 'consulta_basica' aparece en la sección de escenarios.
     */
    #[Test]
    public function tf_demo_03_loader_rechaza_consulta_basica_en_escenarios(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/rol.*consulta_basica|intervencion/');

        $loader = new DemoWorldLoader(sys_get_temp_dir());

        $yaml = <<<YAML
meta:
  nombre: "Test"
  descripcion: "Test consulta basica en escenarios"

centros:
  - id: c1
    nombre: CSS Test
    tipo: asp
    distrito: Centro

profesionales:
  - login: auxiliar@test.es
    nombre: Auxiliar Test
    rol: consulta_basica
    centro: c1

escenarios:
  - profesional: auxiliar@test.es
    ciudadanos:
      - escenario: activa
        cantidad: 1
YAML;

        $tempFile = sys_get_temp_dir() . '/consulta_basica_escenarios.yaml';
        file_put_contents($tempFile, $yaml);

        try {
            $loader->load('consulta_basica_escenarios');
        } finally {
            @unlink($tempFile);
        }
    }

    // -------------------------------------------------------------------------
    // TF-DEMO-04: loader rechaza supervisor en escenarios
    // -------------------------------------------------------------------------

    /**
     * TF-DEMO-04: El loader rechaza un YAML donde un profesional con rol
     * 'supervisor' aparece en la sección de escenarios.
     */
    #[Test]
    public function tf_demo_04_loader_rechaza_supervisor_en_escenarios(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/rol.*supervisor|intervencion/');

        $loader = new DemoWorldLoader(sys_get_temp_dir());

        $yaml = <<<YAML
meta:
  nombre: "Test"
  descripcion: "Test supervisor en escenarios"

centros:
  - id: c1
    nombre: CSS Test
    tipo: asp
    distrito: Centro

profesionales:
  - login: supervisor@test.es
    nombre: Supervisora Test
    rol: supervisor
    centro: c1

escenarios:
  - profesional: supervisor@test.es
    ciudadanos:
      - escenario: activa
        cantidad: 1
YAML;

        $tempFile = sys_get_temp_dir() . '/supervisor_escenarios.yaml';
        file_put_contents($tempFile, $yaml);

        try {
            $loader->load('supervisor_escenarios');
        } finally {
            @unlink($tempFile);
        }
    }

    // -------------------------------------------------------------------------
    // TF-DEMO-05: demo:reset falla en production
    // -------------------------------------------------------------------------

    /**
     * TF-DEMO-05: El comando demo:reset devuelve código de salida 1
     * cuando el entorno es 'production'.
     */
    #[Test]
    public function tf_demo_05_demo_reset_falla_en_production(): void
    {
        // Simular entorno de producción
        $this->app['env'] = 'production';
        app()->bind('env', fn () => 'production');

        $exitCode = \Illuminate\Support\Facades\Artisan::call('demo:reset', ['--world' => 'ci_minimo']);

        $this->assertSame(1, $exitCode);
    }

    // -------------------------------------------------------------------------
    // TF-DEMO-06: demo:validate código 0 con YAML válido
    // -------------------------------------------------------------------------

    /**
     * TF-DEMO-06: El comando demo:validate devuelve código 0 con el
     * mundo ci_minimo (YAML válido).
     */
    #[Test]
    public function tf_demo_06_demo_validate_codigo_0_con_yaml_valido(): void
    {
        $exitCode = \Illuminate\Support\Facades\Artisan::call('demo:validate', ['world' => 'ci_minimo']);

        $this->assertSame(0, $exitCode);
    }

    // -------------------------------------------------------------------------
    // TF-DEMO-07: demo:validate código 1 con YAML inválido
    // -------------------------------------------------------------------------

    /**
     * TF-DEMO-07: El comando demo:validate devuelve código 1 cuando el
     * nombre del mundo no existe.
     */
    #[Test]
    public function tf_demo_07_demo_validate_codigo_1_con_yaml_invalido(): void
    {
        $exitCode = \Illuminate\Support\Facades\Artisan::call('demo:validate', ['world' => 'mundo_que_no_existe_xyz']);

        $this->assertSame(1, $exitCode);
    }

    // -------------------------------------------------------------------------
    // TF-DEMO-08 a TF-DEMO-12: tests de integración pesados (pendientes)
    // -------------------------------------------------------------------------

    /**
     * TF-DEMO-08: demo:reset con ci_minimo crea las UOs correctas.
     *
     * Test de integración pesado: requiere BD de demo aislada con roles Spatie
     * ya sembrados, y que el entorno no sea 'testing' para que el command
     * no sea bloqueado por verificación de entorno.
     * Pendiente de implementar en entorno de demo aislado.
     *
     * @see docs/instrucciones-cli/demo-worlds-cli.md §TF-DEMO-08
     */
    #[Test]
    public function tf_demo_08_demo_reset_crea_uos_correctas(): void
    {
        $this->markTestIncomplete(
            'TF-DEMO-08: Test de integración pesado. ' .
            'Requiere entorno de demo aislado con roles Spatie sembrados y ' .
            'verificación de que APP_ENV !== testing no bloquea el comando. ' .
            'Pendiente de implementar — ver BACKLOG.md.'
        );
    }

    /**
     * TF-DEMO-09: demo:reset con ci_minimo crea los profesionales con roles correctos.
     *
     * Test de integración pesado: requiere BD de demo aislada con roles Spatie
     * ya sembrados. Pendiente de implementar en entorno de demo aislado.
     *
     * @see docs/instrucciones-cli/demo-worlds-cli.md §TF-DEMO-09
     */
    #[Test]
    public function tf_demo_09_demo_reset_crea_profesionales_con_roles(): void
    {
        $this->markTestIncomplete(
            'TF-DEMO-09: Test de integración pesado. ' .
            'Requiere roles Spatie sembrados y entorno de demo aislado. ' .
            'Pendiente de implementar — ver BACKLOG.md.'
        );
    }

    /**
     * TF-DEMO-10: demo:reset genera ciudadanos con el escenario correcto.
     *
     * Test de integración pesado: verifica que TrayectoriaActiva crea los
     * registros esperados en BD. Pendiente de implementar.
     *
     * @see docs/instrucciones-cli/demo-worlds-cli.md §TF-DEMO-10
     */
    #[Test]
    public function tf_demo_10_demo_reset_genera_ciudadanos_con_escenario_correcto(): void
    {
        $this->markTestIncomplete(
            'TF-DEMO-10: Test de integración pesado. ' .
            'Requiere entorno de demo aislado y datos de ciudadanos. ' .
            'Pendiente de implementar — ver BACKLOG.md.'
        );
    }

    /**
     * TF-DEMO-11: Los invariantes de dominio no se violan tras un reset.
     *
     * Test de integración pesado: verifica que DemoInvariantChecker no
     * detecta violaciones tras ejecutar demo:reset. Pendiente de implementar.
     *
     * @see docs/instrucciones-cli/demo-worlds-cli.md §TF-DEMO-11
     */
    #[Test]
    public function tf_demo_11_invariantes_no_violados_tras_reset(): void
    {
        $this->markTestIncomplete(
            'TF-DEMO-11: Test de integración pesado. ' .
            'Requiere entorno de demo aislado para verificar DemoInvariantChecker. ' .
            'Pendiente de implementar — ver BACKLOG.md.'
        );
    }

    /**
     * TF-DEMO-12: TrayectoriaCompleja falla con UO tipo css (no especializada).
     *
     * Test de integración pesado: verifica que el constructor de escenario
     * lanza LogicException si la UO no es especializada. Requiere BD.
     *
     * @see docs/instrucciones-cli/demo-worlds-cli.md §TF-DEMO-12
     */
    #[Test]
    public function tf_demo_12_trayectoria_compleja_falla_con_uo_no_especializada(): void
    {
        $this->markTestIncomplete(
            'TF-DEMO-12: Test de integración pesado. ' .
            'Requiere instanciar UO en BD y verificar LogicException de TrayectoriaCompleja. ' .
            'Pendiente de implementar — ver BACKLOG.md.'
        );
    }
}
