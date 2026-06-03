<?php

namespace Database\Factories;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para la Historia Social (stub).
 *
 * @extends Factory<HistoriaSocial>
 */
class HistoriaSocialFactory extends Factory
{
    /** @var class-string<HistoriaSocial> */
    protected $model = HistoriaSocial::class;

    /**
     * Estado por defecto de la Historia Social.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ciudadano_id' => fake()->numberBetween(1, 99999),
            'unidad_organizativa_id' => UnidadOrganizativa::factory(),
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ];
    }

    /**
     * Historia de ciudadano especialmente protegido.
     */
    public function ciudadanoProtegido(): static
    {
        return $this->state(['ciudadano_protegido' => true]);
    }

    /**
     * Historia asociada a una UO específica.
     */
    public function enUo(UnidadOrganizativa $uo): static
    {
        return $this->state(['unidad_organizativa_id' => $uo->id]);
    }
}
