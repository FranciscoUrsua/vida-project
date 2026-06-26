<?php

namespace Modules\Centro\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\Sala;

/**
 * Factory para la entidad Sala.
 *
 * @extends Factory<Sala>
 */
class SalaFactory extends Factory
{
    /** @var class-string<Sala> */
    protected $model = Sala::class;

    public function definition(): array
    {
        return [
            "centro_id" => fn () => Centro::create([
                "nombre" => "Centro ".fake()->unique()->numerify("###"),
                "tipo_gestion" => "municipal_directo",
                "fecha_alta" => now()->toDateString(),
            ])->id,
            "nombre" => fake()->randomElement(["Aula A", "Sala de reuniones", "Despacho 1", "Sala polivalente", "Sala multiusos"]),
            "descripcion" => fake()->optional()->sentence(),
            "capacidad" => fake()->optional()->numberBetween(5, 50),
            "accesible" => fake()->boolean(70),
            "activa" => true,
            "notas" => null,
        ];
    }

    /** Estado sala inactiva. */
    public function inactiva(): static
    {
        return $this->state(["activa" => false]);
    }
}
