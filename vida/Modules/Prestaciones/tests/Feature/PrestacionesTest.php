<?php

namespace Modules\Prestaciones\Tests\Feature;

use App\Models\CatalogoSistema;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Prestaciones\Models\Prestacion;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del módulo Prestaciones.
 *
 * Verifican el comportamiento del catálogo, los scopes, el versionado
 * automático y el helper CatalogoSistema::opcionesParaSelect.
 */
class PrestacionesTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function prestacionBase(array $overrides = []): array
    {
        return array_merge([
            'codigo'          => '010101',
            'nombre'          => 'Servicio de información y asesoramiento',
            'tipo_prestacion' => 'servicio',
            'nivel_garantia'  => 'garantizada',
            'activa'          => true,
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Creación
    // -------------------------------------------------------------------------

    #[Test]
    public function se_puede_crear_una_prestacion_con_datos_validos(): void
    {
        $prestacion = Prestacion::create($this->prestacionBase());

        $this->assertDatabaseHas('prestaciones', [
            'codigo'          => '010101',
            'tipo_prestacion' => 'servicio',
            'nivel_garantia'  => 'garantizada',
        ]);

        $this->assertTrue($prestacion->activa);
    }

    #[Test]
    public function se_puede_crear_una_prestacion_economica(): void
    {
        $prestacion = Prestacion::create($this->prestacionBase([
            'codigo'          => '020301',
            'nombre'          => 'Ayuda de Emergencia Social',
            'tipo_prestacion' => 'economica',
            'nivel_garantia'  => 'condicionada',
        ]));

        $this->assertEquals('economica', $prestacion->tipo_prestacion);
        $this->assertEquals('condicionada', $prestacion->nivel_garantia);
    }

    // -------------------------------------------------------------------------
    // Validación de enums
    // -------------------------------------------------------------------------

    #[Test]
    public function tipo_prestacion_rechaza_valores_fuera_del_enum(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Prestacion::create($this->prestacionBase([
            'codigo'          => '999999',
            'tipo_prestacion' => 'invalido',
        ]));
    }

    #[Test]
    public function nivel_garantia_rechaza_valores_fuera_del_enum(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Prestacion::create($this->prestacionBase([
            'codigo'         => '999998',
            'nivel_garantia' => 'invalido',
        ]));
    }

    // -------------------------------------------------------------------------
    // Versionado automático
    // -------------------------------------------------------------------------

    #[Test]
    public function actualizar_una_prestacion_genera_una_version_en_versiones(): void
    {
        $prestacion = Prestacion::create($this->prestacionBase());

        $this->assertDatabaseCount('versiones', 0);

        $prestacion->update(['nombre' => 'Nombre modificado']);

        $this->assertDatabaseCount('versiones', 1);

        $version = Version::first();
        $this->assertEquals(Prestacion::class, $version->versionable_type);
        $this->assertEquals($prestacion->id, $version->versionable_id);
        $this->assertEquals('Servicio de información y asesoramiento', $version->datos['nombre']);
    }

    #[Test]
    public function crear_una_prestacion_no_genera_version(): void
    {
        Prestacion::create($this->prestacionBase());

        // Versionable solo guarda snapshot en updating, no en created
        $this->assertDatabaseCount('versiones', 0);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    #[Test]
    public function scope_activas_filtra_prestaciones_dadas_de_baja(): void
    {
        Prestacion::create($this->prestacionBase(['codigo' => 'ACT01', 'activa' => true]));
        Prestacion::create($this->prestacionBase(['codigo' => 'INA01', 'activa' => false]));

        $activas = Prestacion::activas()->get();

        $this->assertCount(1, $activas);
        $this->assertEquals('ACT01', $activas->first()->codigo);
    }

    #[Test]
    public function scope_de_servicio_filtra_solo_servicios(): void
    {
        Prestacion::create($this->prestacionBase(['codigo' => 'SRV01', 'tipo_prestacion' => 'servicio']));
        Prestacion::create($this->prestacionBase(['codigo' => 'ECO01', 'tipo_prestacion' => 'economica']));

        $servicios = Prestacion::deServicio()->get();

        $this->assertCount(1, $servicios);
        $this->assertEquals('SRV01', $servicios->first()->codigo);
    }

    #[Test]
    public function scope_economicas_filtra_solo_prestaciones_economicas(): void
    {
        Prestacion::create($this->prestacionBase(['codigo' => 'SRV01', 'tipo_prestacion' => 'servicio']));
        Prestacion::create($this->prestacionBase(['codigo' => 'ECO01', 'tipo_prestacion' => 'economica']));

        $economicas = Prestacion::economicas()->get();

        $this->assertCount(1, $economicas);
        $this->assertEquals('ECO01', $economicas->first()->codigo);
    }

    // -------------------------------------------------------------------------
    // CatalogoSistema
    // -------------------------------------------------------------------------

    #[Test]
    public function opciones_para_select_devuelve_el_array_correcto_para_un_grupo(): void
    {
        CatalogoSistema::create(['grupo' => 'test.grupo', 'clave' => 'a', 'etiqueta' => 'Opción A', 'orden' => 1, 'activo' => true]);
        CatalogoSistema::create(['grupo' => 'test.grupo', 'clave' => 'b', 'etiqueta' => 'Opción B', 'orden' => 2, 'activo' => true]);
        CatalogoSistema::create(['grupo' => 'test.grupo', 'clave' => 'c', 'etiqueta' => 'Inactiva',  'orden' => 3, 'activo' => false]);

        $opciones = CatalogoSistema::opcionesParaSelect('test.grupo');

        $this->assertArrayHasKey('a', $opciones);
        $this->assertArrayHasKey('b', $opciones);
        $this->assertArrayNotHasKey('c', $opciones); // inactiva, no debe aparecer
        $this->assertEquals('Opción A', $opciones['a']);
    }

    #[Test]
    public function opciones_para_select_devuelve_array_vacio_para_grupo_inexistente(): void
    {
        $opciones = CatalogoSistema::opcionesParaSelect('grupo.que.no.existe');

        $this->assertIsArray($opciones);
        $this->assertEmpty($opciones);
    }

    #[Test]
    public function opciones_para_select_con_prefijo_filtra_correctamente(): void
    {
        CatalogoSistema::create(['grupo' => 'prestacion.categoria', 'clave' => '0101', 'etiqueta' => 'Categoría 01-01', 'orden' => 1, 'activo' => true]);
        CatalogoSistema::create(['grupo' => 'prestacion.categoria', 'clave' => '0102', 'etiqueta' => 'Categoría 01-02', 'orden' => 2, 'activo' => true]);
        CatalogoSistema::create(['grupo' => 'prestacion.categoria', 'clave' => '0201', 'etiqueta' => 'Categoría 02-01', 'orden' => 3, 'activo' => true]);

        $opciones = CatalogoSistema::opcionesParaSelectConPrefijo('prestacion.categoria', '01');

        $this->assertCount(2, $opciones);
        $this->assertArrayHasKey('0101', $opciones);
        $this->assertArrayHasKey('0102', $opciones);
        $this->assertArrayNotHasKey('0201', $opciones);
    }
}
