<?php

namespace Modules\Prestaciones\Tests\Feature;

use App\Models\CatalogoSistema;
use App\Models\Version;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Prestaciones\Models\PrestacionTipoCentro;
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

    // -------------------------------------------------------------------------
    // Clase 1 (ampliación): CatalogoSistema — nuevos tests
    // -------------------------------------------------------------------------

    #[Test]
    public function se_puede_crear_una_entrada_de_catalogo_con_todos_sus_campos(): void
    {
        CatalogoSistema::create([
            'grupo'    => 'prestacion.objetivo_general',
            'clave'    => '01',
            'etiqueta' => 'Acceso, información y valoración',
            'orden'    => 1,
            'activo'   => true,
        ]);

        $this->assertDatabaseHas('catalogos_sistema', [
            'grupo'    => 'prestacion.objetivo_general',
            'clave'    => '01',
            'etiqueta' => 'Acceso, información y valoración',
            'orden'    => 1,
            'activo'   => true,
        ]);
    }

    #[Test]
    public function no_pueden_existir_dos_entradas_con_el_mismo_grupo_y_clave(): void
    {
        CatalogoSistema::create(['grupo' => 'prestacion.competencia', 'clave' => 'municipal', 'etiqueta' => 'Municipal', 'orden' => 1, 'activo' => true]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        CatalogoSistema::create(['grupo' => 'prestacion.competencia', 'clave' => 'municipal', 'etiqueta' => 'Municipal duplicado', 'orden' => 2, 'activo' => true]);
    }

    #[Test]
    public function opciones_para_select_devuelve_array_ordenado_por_campo_orden(): void
    {
        CatalogoSistema::create(['grupo' => 'prestacion.nivel_atencion', 'clave' => 'c', 'etiqueta' => 'Tercero', 'orden' => 3, 'activo' => true]);
        CatalogoSistema::create(['grupo' => 'prestacion.nivel_atencion', 'clave' => 'a', 'etiqueta' => 'Primero',  'orden' => 1, 'activo' => true]);
        CatalogoSistema::create(['grupo' => 'prestacion.nivel_atencion', 'clave' => 'b', 'etiqueta' => 'Segundo',  'orden' => 2, 'activo' => true]);

        $opciones = CatalogoSistema::opcionesParaSelect('prestacion.nivel_atencion');

        $this->assertCount(3, $opciones);
        $this->assertEquals(['a' => 'Primero', 'b' => 'Segundo', 'c' => 'Tercero'], $opciones);
    }

    #[Test]
    public function desactivar_una_entrada_no_la_borra_fisicamente(): void
    {
        $entrada = CatalogoSistema::create(['grupo' => 'test.baja', 'clave' => 'x', 'etiqueta' => 'Entrada X', 'orden' => 1, 'activo' => true]);

        $entrada->update(['activo' => false]);

        $this->assertDatabaseHas('catalogos_sistema', ['id' => $entrada->id, 'activo' => false]);
        $this->assertNotNull(CatalogoSistema::find($entrada->id));
    }

    // -------------------------------------------------------------------------
    // Clase 2 (ampliación): PrestacionModel — nuevos tests
    // -------------------------------------------------------------------------

    #[Test]
    public function el_codigo_de_prestacion_es_unico(): void
    {
        Prestacion::create($this->prestacionBase(['codigo' => '010101']));

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        Prestacion::create($this->prestacionBase(['codigo' => '010101', 'nombre' => 'Duplicado']));
    }

    #[Test]
    public function poblacion_destinataria_se_almacena_y_recupera_como_array(): void
    {
        $prestacion = Prestacion::create($this->prestacionBase([
            'poblacion_destinataria' => ['infancia', 'familia'],
        ]));

        $recuperada = Prestacion::find($prestacion->id);

        $this->assertIsArray($recuperada->poblacion_destinataria);
        $this->assertContains('infancia', $recuperada->poblacion_destinataria);
        $this->assertContains('familia', $recuperada->poblacion_destinataria);
    }

    #[Test]
    public function modalidades_se_almacena_y_recupera_como_array(): void
    {
        $prestacion = Prestacion::create($this->prestacionBase([
            'modalidades' => ['presencial', 'telematica'],
        ]));

        $recuperada = Prestacion::find($prestacion->id);

        $this->assertIsArray($recuperada->modalidades);
        $this->assertContains('presencial', $recuperada->modalidades);
        $this->assertContains('telematica', $recuperada->modalidades);
    }

    #[Test]
    public function la_baja_logica_no_borra_el_registro_fisicamente(): void
    {
        $prestacion = Prestacion::create($this->prestacionBase(['activa' => true]));

        $prestacion->update(['activa' => false]);

        $recuperada = Prestacion::find($prestacion->id);

        $this->assertNotNull($recuperada);
        $this->assertFalse($recuperada->activa);
    }

    // -------------------------------------------------------------------------
    // Clase 3: PrestacionTipoCentroTest
    // -------------------------------------------------------------------------

    #[Test]
    public function una_prestacion_puede_tener_multiples_tipos_de_centro(): void
    {
        $prestacion = Prestacion::create($this->prestacionBase());

        $prestacion->tiposCentro()->create(['tipo_centro' => 'css_general']);
        $prestacion->tiposCentro()->create(['tipo_centro' => 'centro_dia']);

        $this->assertEquals(2, $prestacion->tiposCentro()->count());
    }

    #[Test]
    public function no_pueden_existir_dos_registros_con_la_misma_prestacion_y_tipo_centro(): void
    {
        $prestacion = Prestacion::create($this->prestacionBase());
        $prestacion->tiposCentro()->create(['tipo_centro' => 'css_general']);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        $prestacion->tiposCentro()->create(['tipo_centro' => 'css_general']);
    }

    #[Test]
    public function eliminar_una_prestacion_elimina_en_cascada_sus_tipos_de_centro(): void
    {
        $prestacion = Prestacion::create($this->prestacionBase());
        $prestacion->tiposCentro()->create(['tipo_centro' => 'css_general']);
        $prestacion->tiposCentro()->create(['tipo_centro' => 'centro_dia']);

        $this->assertDatabaseCount('prestacion_tipo_centro', 2);

        // forceDelete dispara el cascade FK que borra los registros en prestacion_tipo_centro
        // El soft delete (delete()) solo pone deleted_at y no dispara el cascade de FK
        $prestacion->forceDelete();

        $this->assertDatabaseCount('prestacion_tipo_centro', 0);
    }

    // -------------------------------------------------------------------------
    // Clase 4 (ampliación): PrestacionVersionado — nuevos tests
    // -------------------------------------------------------------------------

    #[Test]
    public function el_snapshot_contiene_el_estado_completo_anterior_no_solo_el_campo_modificado(): void
    {
        $prestacion = Prestacion::create($this->prestacionBase([
            'codigo'         => 'SNAP01',
            'nombre'         => 'Nombre completo',
            'tipo_prestacion' => 'servicio',
            'nivel_garantia' => 'garantizada',
        ]));

        $prestacion->update(['nombre' => 'Nombre cambiado']);

        $version = Version::first();

        $this->assertArrayHasKey('codigo', $version->datos);
        $this->assertArrayHasKey('nombre', $version->datos);
        $this->assertArrayHasKey('tipo_prestacion', $version->datos);
        $this->assertArrayHasKey('nivel_garantia', $version->datos);
        $this->assertEquals('Nombre completo', $version->datos['nombre']);
    }

    #[Test]
    public function multiples_ediciones_generan_multiples_versiones(): void
    {
        $prestacion = Prestacion::create($this->prestacionBase(['nombre' => 'Versión 0']));

        $prestacion->update(['nombre' => 'Versión 1']);
        $prestacion->update(['nombre' => 'Versión 2']);
        $prestacion->update(['nombre' => 'Versión 3']);

        $this->assertDatabaseCount('versiones', 3);

        $versiones = $prestacion->versiones()->orderBy('id')->get();

        $this->assertEquals('Versión 0', $versiones[0]->datos['nombre']);
        $this->assertEquals('Versión 1', $versiones[1]->datos['nombre']);
        $this->assertEquals('Versión 2', $versiones[2]->datos['nombre']);
    }

    #[Test]
    public function se_puede_reconstruir_el_estado_de_una_prestacion_en_una_fecha_pasada(): void
    {
        $prestacion = Prestacion::create($this->prestacionBase(['nombre' => 'Estado A']));

        // T1: A→B; la versión captura el Estado A con timestamp T1
        Carbon::setTestNow(now()->subMinutes(10));
        $t1 = now();
        $prestacion->update(['nombre' => 'Estado B']);

        // T2: B→C; la versión captura el Estado B con timestamp T2
        Carbon::setTestNow(now()->addMinutes(5)); // T2 = T1 + 5 min
        $t2 = now();
        $prestacion->update(['nombre' => 'Estado C']);

        Carbon::setTestNow(null);

        // Consultando entre T1 y T2 debe devolver la versión del Estado A (capturada en T1)
        $versionEnT1 = $prestacion->versiones()
            ->where('created_at', '<=', $t1)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($versionEnT1);
        $this->assertEquals('Estado A', $versionEnT1->datos['nombre']);
    }

    #[Test]
    public function dar_de_baja_una_prestacion_genera_version_con_activa_true_en_snapshot(): void
    {
        $prestacion = Prestacion::create($this->prestacionBase(['activa' => true]));

        $prestacion->update(['activa' => false]);

        $version = Version::first();

        $this->assertTrue((bool) $version->datos['activa']);
    }
}
