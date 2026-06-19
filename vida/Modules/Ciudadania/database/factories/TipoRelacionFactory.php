<?php

namespace Modules\Ciudadania\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Ciudadania\Models\TipoRelacion;

/**
 * @extends Factory<TipoRelacion>
 */
class TipoRelacionFactory extends Factory
{
    protected $model = TipoRelacion::class;

    public function definition(): array
    {
        $slug = $this->faker->unique()->slug(2).'_'.$this->faker->randomNumber(3);

        return [
            'slug' => $slug,
            'etiqueta' => ucfirst($this->faker->word()),
            'etiqueta_reciproca' => ucfirst($this->faker->word()),
            'slug_reciproco' => null,
            'simetrica' => false,
            'implicacion_funcional' => null,
            'eliminable' => true,
            'activo' => true,
        ];
    }

    public function simetrico(): static
    {
        return $this->state(fn () => ['simetrica' => true, 'slug_reciproco' => null]);
    }

    public function conImplicacion(string $implicacion): static
    {
        return $this->state(fn () => ['implicacion_funcional' => $implicacion]);
    }

    public function noEliminable(): static
    {
        return $this->state(fn () => ['eliminable' => false]);
    }
}
