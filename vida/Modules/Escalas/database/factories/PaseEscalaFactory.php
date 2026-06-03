<?php

namespace Modules\Escalas\Database\Factories;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Escalas\Enums\EstadoPase;
use Modules\Escalas\Models\PaseEscala;
use Modules\Escalas\Models\TipoEscala;

/**
 * @extends Factory<PaseEscala>
 */
class PaseEscalaFactory extends Factory
{
    protected $model = PaseEscala::class;

    public function definition(): array
    {
        return [
            'tipo_escala_id' => TipoEscala::factory(),
            'historia_id' => HistoriaSocial::factory(),
            'profesional_id' => User::factory(),
            'fecha' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'respuestas' => [
                'sec_1' => ['item_1_1' => 5, 'item_1_2' => 10],
            ],
            'score_total' => null,
            'scores_seccion' => null,
            'interpretacion_codigo' => null,
            'notas' => null,
            'estado' => EstadoPase::Borrador,
            'ficha_id' => null,
            'entrevista_id' => null,
        ];
    }

    /** Estado: completado con scores calculados */
    public function completado(): static
    {
        return $this->state([
            'estado' => EstadoPase::Completado,
            'score_total' => 15,
            'scores_seccion' => ['sec_1' => 15],
            'interpretacion_codigo' => 'alto',
        ]);
    }
}
