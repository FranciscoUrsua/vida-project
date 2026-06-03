<?php

namespace Database\Factories;

use App\Models\Api\PerfilAnonimizacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerfilAnonimizacion>
 */
class PerfilAnonimizacionFactory extends Factory
{
    protected $model = PerfilAnonimizacion::class;

    public function definition(): array
    {
        return [
            'nombre' => 'perfil_'.$this->faker->unique()->lexify('????????'),
            'nivel' => 2,
            'version' => 1,
            'estado' => 'activo',
            'es_sistema' => false,
            'campos' => [
                ['campo' => 'nombre', 'tecnica' => 'suprimir'],
            ],
            'k_valor' => null,
        ];
    }

    /**
     * Perfil supervision_interna (Nivel 1 — seudonimización).
     */
    public function supervisionInterna(): static
    {
        return $this->state([
            'nombre' => 'supervision_interna',
            'nivel' => 1,
            'es_sistema' => true,
            'campos' => [
                ['campo' => 'id',                  'tecnica' => 'seudonimizar'],
                ['campo' => 'nombre',              'tecnica' => 'suprimir'],
                ['campo' => 'apellido1',           'tecnica' => 'suprimir'],
                ['campo' => 'apellido2',           'tecnica' => 'suprimir'],
                ['campo' => 'documento_identidad', 'tecnica' => 'suprimir'],
                ['campo' => 'telefono',            'tecnica' => 'suprimir'],
                ['campo' => 'email',               'tecnica' => 'suprimir'],
            ],
            'k_valor' => null,
        ]);
    }

    /**
     * Perfil analitica_interna (Nivel 2 — generalización).
     */
    public function analiticaInterna(): static
    {
        return $this->state([
            'nombre' => 'analitica_interna',
            'nivel' => 2,
            'es_sistema' => true,
            'campos' => [
                ['campo' => 'nombre',              'tecnica' => 'suprimir'],
                ['campo' => 'apellido1',           'tecnica' => 'suprimir'],
                ['campo' => 'apellido2',           'tecnica' => 'suprimir'],
                ['campo' => 'documento_identidad', 'tecnica' => 'suprimir'],
                ['campo' => 'telefono',            'tecnica' => 'suprimir'],
                ['campo' => 'email',               'tecnica' => 'suprimir'],
                ['campo' => 'fecha_nacimiento',    'tecnica' => 'generalizar', 'precision' => 'anio'],
                [
                    'campo' => 'nombre_via',
                    'tecnica' => 'generalizar',
                    'precision' => 'calle_sin_numero',
                    'prerequisito' => 'direccion_normalizada',
                    'fallback' => 'suprimir',
                ],
                ['campo' => 'numero',       'tecnica' => 'suprimir'],
                ['campo' => 'portal',       'tecnica' => 'suprimir'],
                ['campo' => 'escalera',     'tecnica' => 'suprimir'],
                ['campo' => 'piso',         'tecnica' => 'suprimir'],
                ['campo' => 'puerta',       'tecnica' => 'suprimir'],
                ['campo' => 'codigo_postal', 'tecnica' => 'generalizar', 'precision' => 'distrito_proxy'],
                ['campo' => 'sexo',         'tecnica' => 'mantener'],
            ],
            'k_valor' => null,
        ]);
    }

    /**
     * Perfil datos_abiertos (Nivel 3 — k-anonimato, K=10).
     */
    public function datosAbiertos(): static
    {
        return $this->analiticaInterna()->state([
            'nombre' => 'datos_abiertos',
            'nivel' => 3,
            'k_valor' => 10,
        ]);
    }

    /**
     * Perfil investigacion_externa (Nivel 3 — k-anonimato, K=10).
     */
    public function investigacionExterna(): static
    {
        return $this->analiticaInterna()->state([
            'nombre' => 'investigacion_externa',
            'nivel' => 3,
            'k_valor' => 10,
        ]);
    }

    /**
     * Perfil inactivo.
     */
    public function inactivo(): static
    {
        return $this->state(['estado' => 'inactivo']);
    }
}
