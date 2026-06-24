<?php

namespace Database\Seeders;

use App\Models\Api\PerfilAnonimizacion;
use Illuminate\Database\Seeder;

/**
 * Crea los cuatro perfiles de anonimización predefinidos del sistema.
 *
 * Estos perfiles son invariantes del contrato del sistema (es_sistema = true)
 * y no pueden eliminarse desde el backoffice. Los campos del perfil
 * analitica_interna siguen la estructura del JSON de ejemplo de
 * docs/anonimizacion.md § 5, adaptada a los nombres de campo del modelo
 * Ciudadano (apellido1/apellido2 en lugar de apellidos — ver CHANGELOG).
 *
 * Ver docs/anonimizacion.md §§ 4 y 5.
 */
class PerfilesAnonimizacionSeeder extends Seeder
{
    public function run(): void
    {
        $this->crearSupervisionInterna();
        $this->crearAnaliticaInterna();
        $this->crearDatosAbiertos();
        $this->crearInvestigacionExterna();
    }

    /**
     * Nivel 1 — Seudonimización.
     * Caso de uso: revisión de carga de trabajo y casos por supervisores.
     */
    private function crearSupervisionInterna(): void
    {
        PerfilAnonimizacion::firstOrCreate(
            ['nombre' => 'supervision_interna'],
            [
                'nivel' => 1,
                'version' => 1,
                'estado' => 'activo',
                'es_sistema' => true,
                'campos' => [
                    ['campo' => 'id',                  'tecnica' => 'seudonimizar'],
                    ['campo' => 'nombre',              'tecnica' => 'suprimir'],
                    ['campo' => 'apellido1',           'tecnica' => 'suprimir'],
                    ['campo' => 'apellido2',           'tecnica' => 'suprimir'],

                    ['campo' => 'telefono',            'tecnica' => 'suprimir'],
                    ['campo' => 'email',               'tecnica' => 'suprimir'],
                ],
                'k_valor' => null,
            ]
        );
    }

    /**
     * Nivel 2 — Generalización.
     * Caso de uso: datalake municipal, reporting interno.
     *
     * La estructura de campos sigue el JSON de ejemplo de docs/anonimizacion.md § 5.
     */
    private function crearAnaliticaInterna(): void
    {
        PerfilAnonimizacion::firstOrCreate(
            ['nombre' => 'analitica_interna'],
            [
                'nivel' => 2,
                'version' => 1,
                'estado' => 'activo',
                'es_sistema' => true,
                'campos' => [
                    ['campo' => 'nombre',              'tecnica' => 'suprimir'],
                    ['campo' => 'apellido1',           'tecnica' => 'suprimir'],
                    ['campo' => 'apellido2',           'tecnica' => 'suprimir'],

                    ['campo' => 'telefono',            'tecnica' => 'suprimir'],
                    ['campo' => 'email',               'tecnica' => 'suprimir'],
                    [
                        'campo' => 'fecha_nacimiento',
                        'tecnica' => 'generalizar',
                        'precision' => 'anio',
                    ],
                    [
                        'campo' => 'nombre_via',
                        'tecnica' => 'generalizar',
                        'precision' => 'calle_sin_numero',
                        'prerequisito' => 'direccion_normalizada',
                        'fallback' => 'suprimir',
                    ],
                    ['campo' => 'numero',   'tecnica' => 'suprimir'],
                    ['campo' => 'portal',   'tecnica' => 'suprimir'],
                    ['campo' => 'escalera', 'tecnica' => 'suprimir'],
                    ['campo' => 'piso',     'tecnica' => 'suprimir'],
                    ['campo' => 'puerta',   'tecnica' => 'suprimir'],
                    [
                        'campo' => 'codigo_postal',
                        'tecnica' => 'generalizar',
                        'precision' => 'distrito_proxy',
                    ],
                    ['campo' => 'sexo', 'tecnica' => 'mantener'],
                ],
                'k_valor' => null,
            ]
        );
    }

    /**
     * Nivel 3 — K-anonimato con K=10.
     * Caso de uso: portal de datos abiertos del Ayuntamiento.
     */
    private function crearDatosAbiertos(): void
    {
        PerfilAnonimizacion::firstOrCreate(
            ['nombre' => 'datos_abiertos'],
            [
                'nivel' => 3,
                'version' => 1,
                'estado' => 'activo',
                'es_sistema' => true,
                'campos' => [
                    ['campo' => 'nombre',              'tecnica' => 'suprimir'],
                    ['campo' => 'apellido1',           'tecnica' => 'suprimir'],
                    ['campo' => 'apellido2',           'tecnica' => 'suprimir'],

                    ['campo' => 'telefono',            'tecnica' => 'suprimir'],
                    ['campo' => 'email',               'tecnica' => 'suprimir'],
                    [
                        'campo' => 'fecha_nacimiento',
                        'tecnica' => 'generalizar',
                        'precision' => 'anio',
                    ],
                    [
                        'campo' => 'nombre_via',
                        'tecnica' => 'generalizar',
                        'precision' => 'calle_sin_numero',
                        'prerequisito' => 'direccion_normalizada',
                        'fallback' => 'suprimir',
                    ],
                    ['campo' => 'numero',   'tecnica' => 'suprimir'],
                    ['campo' => 'portal',   'tecnica' => 'suprimir'],
                    ['campo' => 'escalera', 'tecnica' => 'suprimir'],
                    ['campo' => 'piso',     'tecnica' => 'suprimir'],
                    ['campo' => 'puerta',   'tecnica' => 'suprimir'],
                    [
                        'campo' => 'codigo_postal',
                        'tecnica' => 'generalizar',
                        'precision' => 'distrito_proxy',
                    ],
                    ['campo' => 'sexo', 'tecnica' => 'mantener'],
                ],
                // K=10 por defecto para datos abiertos — ver docs/anonimizacion.md § 8
                'k_valor' => 10,
            ]
        );
    }

    /**
     * Nivel 3 — K-anonimato con K=10.
     * Caso de uso: cesión a investigadores con convenio.
     *
     * // TODO: evaluar si puede reducirse a K=5 con salvaguardas adicionales
     *          (acuerdo de confidencialidad, acceso controlado). Ver docs/anonimizacion.md § 8.
     */
    private function crearInvestigacionExterna(): void
    {
        PerfilAnonimizacion::firstOrCreate(
            ['nombre' => 'investigacion_externa'],
            [
                'nivel' => 3,
                'version' => 1,
                'estado' => 'activo',
                'es_sistema' => true,
                'campos' => [
                    ['campo' => 'nombre',              'tecnica' => 'suprimir'],
                    ['campo' => 'apellido1',           'tecnica' => 'suprimir'],
                    ['campo' => 'apellido2',           'tecnica' => 'suprimir'],

                    ['campo' => 'telefono',            'tecnica' => 'suprimir'],
                    ['campo' => 'email',               'tecnica' => 'suprimir'],
                    [
                        'campo' => 'fecha_nacimiento',
                        'tecnica' => 'generalizar',
                        'precision' => 'anio',
                    ],
                    [
                        'campo' => 'nombre_via',
                        'tecnica' => 'generalizar',
                        'precision' => 'calle_sin_numero',
                        'prerequisito' => 'direccion_normalizada',
                        'fallback' => 'suprimir',
                    ],
                    ['campo' => 'numero',   'tecnica' => 'suprimir'],
                    ['campo' => 'portal',   'tecnica' => 'suprimir'],
                    ['campo' => 'escalera', 'tecnica' => 'suprimir'],
                    ['campo' => 'piso',     'tecnica' => 'suprimir'],
                    ['campo' => 'puerta',   'tecnica' => 'suprimir'],
                    [
                        'campo' => 'codigo_postal',
                        'tecnica' => 'generalizar',
                        'precision' => 'distrito_proxy',
                    ],
                    ['campo' => 'sexo', 'tecnica' => 'mantener'],
                ],
                'k_valor' => 10,
            ]
        );
    }
}
