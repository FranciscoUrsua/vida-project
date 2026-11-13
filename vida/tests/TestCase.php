<?php

namespace Tests;

use App\Models\UnidadOrganizativa;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Modules\Centro\Models\Centro;
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

    /**
     * Centro de la UO (relación 1:1, RN-14); lo crea la primera vez.
     *
     * Atender a ciudadanos exige estar adscrito a la UO de un centro (RN-15): los
     * tests que crean un profesional adscrito a una UO y le hacen abrir historias,
     * registrar apuntes o dar citas llaman a esto para que esa UO tenga centro.
     *
     * @param UnidadOrganizativa|int $uo UO del centro.
     * @param string $codigoTipo Código del tipo de centro si hay que crearlo.
     */
    protected function centroParaUo(UnidadOrganizativa|int $uo, string $codigoTipo = 'css_general'): Centro
    {
        $uoId = $uo instanceof UnidadOrganizativa ? $uo->id : $uo;

        return Centro::where('unidad_organizativa_id', $uoId)->first()
            ?? Centro::create([
                'tipo_centro_id' => $this->tipoCentroId($codigoTipo),
                'unidad_organizativa_id' => $uoId,
                'nombre' => 'Centro de '.UnidadOrganizativa::find($uoId)?->nombre,
                'tipo_gestion' => 'municipal_directo',
                'fecha_alta' => '2020-01-01',
            ]);
    }
}
