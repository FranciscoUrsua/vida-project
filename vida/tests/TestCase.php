<?php

namespace Tests;

use App\Models\UnidadOrganizativa;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Modules\Centro\Models\TipoCentro;

/**
 * Caso base de los tests de la aplicación.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Id de un tipo de centro de prueba con ese código; lo crea la primera vez.
     *
     * Todo centro tiene tipo (RN-01 de docs/modulo-tipo-centro.md): los tests que
     * solo necesitan «un centro» lo crean con `'tipo_centro_id' => $this->tipoCentroId()`.
     * Los que prueban la configuración del tipo usan sus propios datos
     * (TipoCentroTestSetup).
     *
     * @param string $codigo Código del tipo (p. ej. `css_general`, `ciam`).
     * @param array<string, mixed> $atributos Configuración del tipo si hay que crearlo.
     *
     * @return int
     */
    protected function tipoCentroId(string $codigo = 'css_general', array $atributos = []): int
    {
        $existente = TipoCentro::where('codigo', $codigo)->value('id');
        if ($existente !== null) {
            return (int) $existente;
        }

        $uoFuncional = UnidadOrganizativa::firstOrCreate(
            ['nombre' => 'UO funcional de pruebas'],
            ['tipo' => 'departamento', 'parent_id' => null, 'activa' => true],
        );

        return TipoCentro::create(array_merge([
            'codigo' => $codigo,
            'nombre' => 'Tipo '.$codigo,
            'siglas' => mb_strtoupper(mb_substr($codigo, 0, 20)),
            'unidad_organizativa_id' => $uoFuncional->id,
            'fecha_alta' => '2020-01-01',
        ], $atributos))->id;
    }
}
