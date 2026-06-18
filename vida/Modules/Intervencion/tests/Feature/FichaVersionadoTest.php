<?php

namespace Modules\Intervencion\Tests\Feature;

use App\Models\HistoriaSocial;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\TipoFicha;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales de Ficha con schema_snapshot y versionado.
 *
 * TF-INT-I01 a TF-INT-I12
 *
 * @see docs/instrucciones-cli/ficha-schema-snapshot.md
 * @see docs/modulo-intervencion.md §4.5–4.7
 */
class FichaVersionadoTest extends TestCase
{
    use RefreshDatabase;

    protected TipoFicha $tipoFicha;

    protected HistoriaSocial $historia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tipoFicha = TipoFicha::factory()->create([
            'schema' => ['campos' => [
                ['id' => 'campo_a', 'tipo' => 'numero', 'etiqueta' => 'Campo A',
                    'obligatorio' => true, 'orden' => 1],
                ['id' => 'campo_b', 'tipo' => 'texto', 'etiqueta' => 'Campo B',
                    'obligatorio' => false, 'orden' => 2],
            ]],
            'activo' => true,
        ]);

        $this->historia = HistoriaSocial::factory()->create();
    }

    /**
     * TF-INT-I01: Al crear una Ficha se guarda schema_snapshot igual al schema del TipoFicha.
     */
    #[Test]
    public function i01_crear_ficha_guarda_schema_snapshot(): void
    {
        $ficha = Ficha::create([
            'historia_id'     => $this->historia->id,
            'tipo_ficha_id'   => $this->tipoFicha->id,
            'schema_snapshot' => $this->tipoFicha->schema,
            'datos'           => ['campo_a' => 100, 'campo_b' => 'texto'],
            'completada'      => false,
        ]);

        $this->assertNotNull($ficha->schema_snapshot);
        $this->assertNotEmpty($ficha->schema_snapshot);
        $this->assertEquals($this->tipoFicha->schema, $ficha->schema_snapshot);
    }

    /**
     * TF-INT-I02: El schema_snapshot no cambia si el TipoFicha se modifica después.
     */
    #[Test]
    public function i02_schema_snapshot_no_cambia_al_modificar_tipo_ficha(): void
    {
        $ficha = Ficha::create([
            'historia_id'     => $this->historia->id,
            'tipo_ficha_id'   => $this->tipoFicha->id,
            'schema_snapshot' => $this->tipoFicha->schema,
            'datos'           => ['campo_a' => 100],
            'completada'      => false,
        ]);

        $snapshotOriginal = $ficha->schema_snapshot;

        // Añadir campo_c al TipoFicha
        $this->tipoFicha->schema = ['campos' => [
            ['id' => 'campo_a', 'tipo' => 'numero', 'etiqueta' => 'Campo A', 'obligatorio' => true, 'orden' => 1],
            ['id' => 'campo_b', 'tipo' => 'texto', 'etiqueta' => 'Campo B', 'obligatorio' => false, 'orden' => 2],
            ['id' => 'campo_c', 'tipo' => 'texto', 'etiqueta' => 'Campo C', 'obligatorio' => false, 'orden' => 3],
        ]];
        $this->tipoFicha->save();

        $snapshotActual = $ficha->fresh()->schema_snapshot;

        $idsSnapshot = collect($snapshotActual['campos'])->pluck('id')->all();
        $this->assertNotContains('campo_c', $idsSnapshot);
        $this->assertEquals($snapshotOriginal, $snapshotActual);
    }

    /**
     * TF-INT-I03: Actualizar una Ficha genera una versión Versionable.
     *
     * Negativo: sin trait Versionable en Ficha, no debe existir ninguna versión.
     */
    #[Test]
    public function i03_correccion_genera_version_versionable(): void
    {
        $ficha = Ficha::create([
            'historia_id'     => $this->historia->id,
            'tipo_ficha_id'   => $this->tipoFicha->id,
            'schema_snapshot' => $this->tipoFicha->schema,
            'datos'           => ['campo_a' => 800],
            'completada'      => false,
        ]);

        $ficha->update(['datos' => ['campo_a' => 950]]);

        $version = Version::where('versionable_type', Ficha::class)
            ->where('versionable_id', $ficha->id)
            ->first();

        $this->assertNotNull($version, 'Debe existir una versión Versionable tras la corrección.');

        $datosAnteriores = $version->datos;
        $this->assertEquals(800, $datosAnteriores['datos']['campo_a'] ?? null);
        $this->assertEquals(950, $ficha->fresh()->datos['campo_a']);
    }

    /**
     * TF-INT-I04: La versión Versionable incluye el schema_snapshot de la ficha.
     */
    #[Test]
    public function i04_version_versionable_incluye_schema_snapshot(): void
    {
        $ficha = Ficha::create([
            'historia_id'     => $this->historia->id,
            'tipo_ficha_id'   => $this->tipoFicha->id,
            'schema_snapshot' => $this->tipoFicha->schema,
            'datos'           => ['campo_a' => 100],
            'completada'      => false,
        ]);

        $ficha->update(['notas' => 'Corrección de notas']);

        $version = Version::where('versionable_type', Ficha::class)
            ->where('versionable_id', $ficha->id)
            ->first();

        $this->assertNotNull($version);
        $this->assertNotEmpty($version->datos['schema_snapshot'] ?? null);
    }

    /**
     * TF-INT-I05: Una nueva valoración crea una Ficha nueva, no modifica la anterior.
     */
    #[Test]
    public function i05_nueva_valoracion_crea_ficha_nueva(): void
    {
        $fichaV1 = Ficha::create([
            'historia_id'     => $this->historia->id,
            'tipo_ficha_id'   => $this->tipoFicha->id,
            'schema_snapshot' => $this->tipoFicha->schema,
            'datos'           => ['campo_a' => 800],
            'completada'      => true,
        ]);

        $fichaV2 = Ficha::create([
            'historia_id'     => $this->historia->id,
            'tipo_ficha_id'   => $this->tipoFicha->id,
            'schema_snapshot' => $this->tipoFicha->schema,
            'datos'           => ['campo_a' => 1100],
            'completada'      => true,
        ]);

        $this->assertCount(2, Ficha::where('historia_id', $this->historia->id)
            ->where('tipo_ficha_id', $this->tipoFicha->id)->get());

        $this->assertEquals(800, $fichaV1->fresh()->datos['campo_a']);
        $this->assertEquals(1100, $fichaV2->fresh()->datos['campo_a']);
    }

    /**
     * TF-INT-I06: El schema_snapshot de la nueva valoración usa el schema actual del TipoFicha.
     */
    #[Test]
    public function i06_nueva_valoracion_usa_schema_actual(): void
    {
        $fichaV1 = Ficha::create([
            'historia_id'     => $this->historia->id,
            'tipo_ficha_id'   => $this->tipoFicha->id,
            'schema_snapshot' => $this->tipoFicha->schema,
            'datos'           => ['campo_a' => 100],
            'completada'      => true,
        ]);

        // Añadir campo_c al TipoFicha
        $this->tipoFicha->schema = ['campos' => [
            ['id' => 'campo_a', 'tipo' => 'numero', 'etiqueta' => 'Campo A', 'obligatorio' => true, 'orden' => 1],
            ['id' => 'campo_b', 'tipo' => 'texto', 'etiqueta' => 'Campo B', 'obligatorio' => false, 'orden' => 2],
            ['id' => 'campo_c', 'tipo' => 'texto', 'etiqueta' => 'Campo C', 'obligatorio' => false, 'orden' => 3],
        ]];
        $this->tipoFicha->save();

        $fichaV2 = Ficha::create([
            'historia_id'     => $this->historia->id,
            'tipo_ficha_id'   => $this->tipoFicha->id,
            'schema_snapshot' => $this->tipoFicha->fresh()->schema,
            'datos'           => ['campo_a' => 200],
            'completada'      => true,
        ]);

        $idsV1 = collect($fichaV1->schema_snapshot['campos'])->pluck('id')->all();
        $idsV2 = collect($fichaV2->schema_snapshot['campos'])->pluck('id')->all();

        $this->assertNotContains('campo_c', $idsV1);
        $this->assertContains('campo_c', $idsV2);
    }

    /**
     * TF-INT-I07: Pre-relleno copia campos comunes de la ficha anterior.
     */
    #[Test]
    public function i07_prerelleno_copia_campos_comunes(): void
    {
        $fichaV1 = Ficha::create([
            'historia_id'     => $this->historia->id,
            'tipo_ficha_id'   => $this->tipoFicha->id,
            'schema_snapshot' => $this->tipoFicha->schema,
            'datos'           => ['campo_a' => 10, 'campo_b' => 'Valor B'],
            'completada'      => true,
        ]);

        $resultado = Ficha::prerellenarDesde($fichaV1, $this->tipoFicha);

        $this->assertEquals(10, $resultado['campo_a']);
        $this->assertEquals('Valor B', $resultado['campo_b']);
    }

    /**
     * TF-INT-I08: Pre-relleno descarta campos retirados del schema actual.
     */
    #[Test]
    public function i08_prerelleno_descarta_campos_retirados(): void
    {
        // Ficha creada con schema que incluía campo_c (ya retirado en el tipo actual)
        $fichaV1 = Ficha::create([
            'historia_id'     => $this->historia->id,
            'tipo_ficha_id'   => $this->tipoFicha->id,
            'schema_snapshot' => ['campos' => [
                ['id' => 'campo_a', 'tipo' => 'numero', 'etiqueta' => 'Campo A', 'obligatorio' => true, 'orden' => 1],
                ['id' => 'campo_c', 'tipo' => 'texto', 'etiqueta' => 'Campo C', 'obligatorio' => false, 'orden' => 3],
            ]],
            'datos'      => ['campo_a' => 1, 'campo_c' => 5],
            'completada' => true,
        ]);

        // tipoFicha actual no tiene campo_c
        $resultado = Ficha::prerellenarDesde($fichaV1, $this->tipoFicha);

        $this->assertEquals(1, $resultado['campo_a']);
        $this->assertArrayNotHasKey('campo_c', $resultado);
    }

    /**
     * TF-INT-I09: Pre-relleno deja null los campos nuevos del schema actual.
     */
    #[Test]
    public function i09_prerelleno_deja_null_campos_nuevos(): void
    {
        // Ficha creada con schema que no tenía campo_b (añadido después)
        $fichaV1 = Ficha::create([
            'historia_id'     => $this->historia->id,
            'tipo_ficha_id'   => $this->tipoFicha->id,
            'schema_snapshot' => ['campos' => [
                ['id' => 'campo_a', 'tipo' => 'numero', 'etiqueta' => 'Campo A', 'obligatorio' => true, 'orden' => 1],
            ]],
            'datos'      => ['campo_a' => 42],
            'completada' => true,
        ]);

        // tipoFicha actual tiene campo_b
        $resultado = Ficha::prerellenarDesde($fichaV1, $this->tipoFicha);

        $this->assertArrayHasKey('campo_b', $resultado);
        $this->assertNull($resultado['campo_b']);
    }

    /**
     * TF-INT-I10: Cambiar el tipo de un campo de TipoFicha con fichas asociadas lanza excepción.
     *
     * Negativo: si se elimina la guardia de inmutabilidad de tipo, este test debe fallar.
     */
    #[Test]
    public function i10_cambiar_tipo_campo_con_fichas_lanza_excepcion(): void
    {
        Ficha::create([
            'historia_id'     => $this->historia->id,
            'tipo_ficha_id'   => $this->tipoFicha->id,
            'schema_snapshot' => $this->tipoFicha->schema,
            'completada'      => false,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->tipoFicha->schema = ['campos' => [
            ['id' => 'campo_a', 'tipo' => 'texto', 'etiqueta' => 'Campo A', 'obligatorio' => true, 'orden' => 1],
            ['id' => 'campo_b', 'tipo' => 'texto', 'etiqueta' => 'Campo B', 'obligatorio' => false, 'orden' => 2],
        ]];
        $this->tipoFicha->save();
    }

    /**
     * TF-INT-I11: Eliminar un campo de TipoFicha con fichas asociadas está permitido.
     *
     * Verifica la inversión de la restricción anterior (H08 en TipoFichaTest).
     * Negativo: si se reactiva la guardia de eliminación, este test debe fallar.
     */
    #[Test]
    public function i11_eliminar_campo_con_fichas_esta_permitido(): void
    {
        Ficha::create([
            'historia_id'     => $this->historia->id,
            'tipo_ficha_id'   => $this->tipoFicha->id,
            'schema_snapshot' => $this->tipoFicha->schema,
            'completada'      => false,
        ]);

        // Eliminar campo_b — no debe lanzar excepción
        $this->tipoFicha->schema = ['campos' => [
            ['id' => 'campo_a', 'tipo' => 'numero', 'etiqueta' => 'Campo A', 'obligatorio' => true, 'orden' => 1],
        ]];
        $this->tipoFicha->save();

        $this->assertCount(1, $this->tipoFicha->fresh()->schema['campos']);
    }

    /**
     * TF-INT-I12: historialPara ordena fichas de más reciente a más antigua.
     */
    #[Test]
    public function i12_historial_para_ordena_por_fecha_descendente(): void
    {
        $fichas = collect([
            '2024-01-10',
            '2024-06-15',
            '2025-03-01',
        ])->map(fn ($fecha) => Ficha::create([
            'historia_id'     => $this->historia->id,
            'tipo_ficha_id'   => $this->tipoFicha->id,
            'schema_snapshot' => $this->tipoFicha->schema,
            'completada'      => true,
            'created_at'      => $fecha,
            'updated_at'      => $fecha,
        ]));

        $historial = Ficha::historialPara($this->historia->id, $this->tipoFicha->id)->get();

        $this->assertCount(3, $historial);
        $this->assertTrue($historial[0]->created_at >= $historial[1]->created_at);
        $this->assertTrue($historial[1]->created_at >= $historial[2]->created_at);
    }
}
