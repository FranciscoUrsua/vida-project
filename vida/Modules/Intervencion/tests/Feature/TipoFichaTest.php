<?php

namespace Modules\Intervencion\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\TipoFicha;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo H: Configuración de TipoFicha.
 *
 * @see docs/instrucciones-cli/tipo-ficha-implementacion.md
 * @see docs/modulo-intervencion.md §4.2, §10 Grupo H
 */
class TipoFichaTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function schemaValido(array $extraCampos = []): array
    {
        return [
            'campos' => array_merge([
                [
                    'id' => 'campo_texto',
                    'tipo' => 'texto',
                    'etiqueta' => 'Campo de texto',
                    'descripcion' => null,
                    'obligatorio' => true,
                    'orden' => 1,
                ],
            ], $extraCampos),
        ];
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * TF-INT-H01: TipoFicha con schema válido se guarda correctamente.
     */
    #[Test]
    public function tipo_ficha_con_schema_valido_se_guarda_correctamente(): void
    {
        TipoFicha::create([
            'nombre' => 'Test',
            'schema' => $this->schemaValido(),
            'activo' => true,
        ]);

        $this->assertEquals(1, TipoFicha::count());
    }

    /**
     * TF-INT-H02: TipoFicha sin clave 'campos' en schema lanza ValidationException.
     *
     * Negativo: si se elimina la validación de booted(), este test debe fallar.
     */
    #[Test]
    public function tipo_ficha_sin_clave_campos_lanza_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        TipoFicha::create([
            'nombre' => 'Test',
            'schema' => ['otra_clave' => []],
            'activo' => true,
        ]);
    }

    /**
     * TF-INT-H03: TipoFicha con tipo de campo inválido lanza ValidationException.
     */
    #[Test]
    public function tipo_ficha_con_tipo_campo_invalido_lanza_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        TipoFicha::create([
            'nombre' => 'Test',
            'schema' => [
                'campos' => [
                    [
                        'id' => 'campo_desplegable',
                        'tipo' => 'desplegable',
                        'etiqueta' => 'Campo inválido',
                        'obligatorio' => false,
                        'orden' => 1,
                    ],
                ],
            ],
            'activo' => true,
        ]);
    }

    /**
     * TF-INT-H04: Campo 'select' sin opciones lanza ValidationException.
     */
    #[Test]
    public function campo_select_sin_opciones_lanza_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        TipoFicha::create([
            'nombre' => 'Test',
            'schema' => [
                'campos' => [
                    [
                        'id' => 'campo_select',
                        'tipo' => 'select',
                        'etiqueta' => 'Selección',
                        'obligatorio' => false,
                        'orden' => 1,
                        // opciones ausente
                    ],
                ],
            ],
            'activo' => true,
        ]);
    }

    /**
     * TF-INT-H05: Campo 'select' con menos de 2 opciones lanza ValidationException.
     */
    #[Test]
    public function campo_select_con_menos_de_dos_opciones_lanza_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        TipoFicha::create([
            'nombre' => 'Test',
            'schema' => [
                'campos' => [
                    [
                        'id' => 'campo_select',
                        'tipo' => 'select',
                        'etiqueta' => 'Selección',
                        'opciones' => ['Solo una'],
                        'obligatorio' => false,
                        'orden' => 1,
                    ],
                ],
            ],
            'activo' => true,
        ]);
    }

    /**
     * TF-INT-H06: Campo 'escala' sin 'tipo_escala_id' lanza ValidationException.
     */
    #[Test]
    public function campo_escala_sin_tipo_escala_id_lanza_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        TipoFicha::create([
            'nombre' => 'Test',
            'schema' => [
                'campos' => [
                    [
                        'id' => 'campo_escala',
                        'tipo' => 'escala',
                        'etiqueta' => 'Resultado escala',
                        'obligatorio' => false,
                        'orden' => 1,
                        // tipo_escala_id ausente
                    ],
                ],
            ],
            'activo' => true,
        ]);
    }

    /**
     * TF-INT-H07: IDs duplicados en el schema lanzan ValidationException.
     */
    #[Test]
    public function ids_duplicados_en_schema_lanzan_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        TipoFicha::create([
            'nombre' => 'Test',
            'schema' => [
                'campos' => [
                    [
                        'id' => 'campo_duplicado',
                        'tipo' => 'texto',
                        'etiqueta' => 'Campo A',
                        'obligatorio' => false,
                        'orden' => 1,
                    ],
                    [
                        'id' => 'campo_duplicado',
                        'tipo' => 'texto',
                        'etiqueta' => 'Campo B',
                        'obligatorio' => false,
                        'orden' => 2,
                    ],
                ],
            ],
            'activo' => true,
        ]);
    }

    /**
     * TF-INT-H08: No se puede eliminar un campo de una ficha con datos asociados.
     *
     * Negativo: si se elimina la guard de inmutabilidad, este test debe fallar.
     */
    #[Test]
    public function no_se_puede_eliminar_campo_de_ficha_con_datos_asociados(): void
    {
        $tipoFicha = TipoFicha::create([
            'nombre' => 'Ficha con dos campos',
            'schema' => [
                'campos' => [
                    ['id' => 'campo_a', 'tipo' => 'texto', 'etiqueta' => 'Campo A', 'obligatorio' => false, 'orden' => 1],
                    ['id' => 'campo_b', 'tipo' => 'texto', 'etiqueta' => 'Campo B', 'obligatorio' => false, 'orden' => 2],
                ],
            ],
            'activo' => true,
        ]);

        // Crear una ficha cumplimentada asociada a este tipo
        Ficha::factory()->create(['tipo_ficha_id' => $tipoFicha->id]);

        $this->assertTrue($tipoFicha->tieneFichasAsociadas());

        $this->expectException(ValidationException::class);

        // Intentar guardar el schema con solo campo_a (eliminando campo_b)
        $tipoFicha->update([
            'schema' => [
                'campos' => [
                    ['id' => 'campo_a', 'tipo' => 'texto', 'etiqueta' => 'Campo A', 'obligatorio' => false, 'orden' => 1],
                ],
            ],
        ]);
    }

    /**
     * TF-INT-H09: No se puede cambiar el tipo de un campo con datos asociados.
     */
    #[Test]
    public function no_se_puede_cambiar_tipo_campo_con_datos_asociados(): void
    {
        $tipoFicha = TipoFicha::create([
            'nombre' => 'Ficha un campo',
            'schema' => [
                'campos' => [
                    ['id' => 'campo_a', 'tipo' => 'texto', 'etiqueta' => 'Campo A', 'obligatorio' => false, 'orden' => 1],
                ],
            ],
            'activo' => true,
        ]);

        Ficha::factory()->create(['tipo_ficha_id' => $tipoFicha->id]);

        $this->expectException(ValidationException::class);

        // Intentar cambiar campo_a de 'texto' a 'numero'
        $tipoFicha->update([
            'schema' => [
                'campos' => [
                    ['id' => 'campo_a', 'tipo' => 'numero', 'etiqueta' => 'Campo A', 'obligatorio' => false, 'orden' => 1],
                ],
            ],
        ]);
    }

    /**
     * TF-INT-H10: Se puede añadir un campo nuevo a una ficha con datos asociados.
     */
    #[Test]
    public function se_puede_anadir_campo_nuevo_a_ficha_con_datos_asociados(): void
    {
        $tipoFicha = TipoFicha::create([
            'nombre' => 'Ficha ampliable',
            'schema' => [
                'campos' => [
                    ['id' => 'campo_a', 'tipo' => 'texto', 'etiqueta' => 'Campo A', 'obligatorio' => false, 'orden' => 1],
                ],
            ],
            'activo' => true,
        ]);

        Ficha::factory()->create(['tipo_ficha_id' => $tipoFicha->id]);

        // Actualizar añadiendo campo_b (manteniendo campo_a intacto)
        $tipoFicha->update([
            'schema' => [
                'campos' => [
                    ['id' => 'campo_a', 'tipo' => 'texto', 'etiqueta' => 'Campo A', 'obligatorio' => false, 'orden' => 1],
                    ['id' => 'campo_b', 'tipo' => 'texto', 'etiqueta' => 'Campo B', 'obligatorio' => false, 'orden' => 2],
                ],
            ],
        ]);

        $tipoFicha->refresh();

        $this->assertCount(2, $tipoFicha->schema['campos']);
    }
}
