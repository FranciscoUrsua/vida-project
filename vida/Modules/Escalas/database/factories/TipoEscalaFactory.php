<?php

namespace Modules\Escalas\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Escalas\Models\TipoEscala;

/**
 * @extends Factory<TipoEscala>
 */
class TipoEscalaFactory extends Factory
{
    protected $model = TipoEscala::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->words(3, true),
            'codigo' => fake()->unique()->slug(2),
            'descripcion' => fake()->sentence(),
            'instrucciones_aplicacion' => fake()->paragraph(),
            'confirmar_instrucciones' => false,
            'fuente' => null,
            'contextos' => ['dependencia'],
            'activa' => true,
            'schema' => [
                'secciones' => [
                    [
                        'id' => 'sec_1',
                        'titulo' => 'Sección de prueba',
                        'instrucciones' => null,
                        'orden' => 1,
                        'items' => [
                            [
                                'id' => 'item_1_1',
                                'texto' => 'Ítem uno',
                                'instrucciones' => null,
                                'orden' => 1,
                                'opciones' => [
                                    ['valor' => 0,  'etiqueta' => 'Bajo'],
                                    ['valor' => 5,  'etiqueta' => 'Medio'],
                                    ['valor' => 10, 'etiqueta' => 'Alto'],
                                ],
                            ],
                            [
                                'id' => 'item_1_2',
                                'texto' => 'Ítem dos',
                                'instrucciones' => null,
                                'orden' => 2,
                                'opciones' => [
                                    ['valor' => 0,  'etiqueta' => 'Bajo'],
                                    ['valor' => 5,  'etiqueta' => 'Medio'],
                                    ['valor' => 10, 'etiqueta' => 'Alto'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'rangos_interpretacion' => [
                'rangos' => [
                    ['desde' => 0,  'hasta' => 10, 'etiqueta' => 'Bajo', 'codigo' => 'bajo'],
                    ['desde' => 11, 'hasta' => 20, 'etiqueta' => 'Alto', 'codigo' => 'alto'],
                ],
                'nota_interpretacion' => null,
            ],
        ];
    }

    /** Estado: escala inactiva */
    public function inactiva(): static
    {
        return $this->state(['activa' => false]);
    }
}
