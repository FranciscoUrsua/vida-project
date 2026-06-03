<?php

namespace Database\Factories;

use App\Models\UnidadOrganizativa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para UnidadOrganizativa.
 *
 * @extends Factory<UnidadOrganizativa>
 */
class UnidadOrganizativaFactory extends Factory
{
    /** @var class-string<UnidadOrganizativa> */
    protected $model = UnidadOrganizativa::class;

    /**
     * Estado por defecto de la Unidad Organizativa.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'CSS '.fake()->city(),
            'tipo' => 'centro',
            'parent_id' => null,
            'activa' => true,
        ];
    }

    /**
     * UO inactiva.
     */
    public function inactiva(): static
    {
        return $this->state(['activa' => false]);
    }

    /**
     * UO hija de otra UO.
     */
    public function hijaDeUo(UnidadOrganizativa $padre): static
    {
        return $this->state([
            'parent_id' => $padre->id,
            'tipo' => 'departamento',
        ]);
    }
}
