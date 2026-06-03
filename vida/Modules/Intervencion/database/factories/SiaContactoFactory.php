<?php

namespace Modules\Intervencion\Database\Factories;

use App\Models\Ciudadano;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Intervencion\Enums\ClasificacionSia;
use Modules\Intervencion\Models\SiaContacto;

/**
 * @extends Factory<SiaContacto>
 */
class SiaContactoFactory extends Factory
{
    protected $model = SiaContacto::class;

    public function definition(): array
    {
        return [
            'ciudadano_id' => Ciudadano::factory(),
            'auxiliar_id' => User::factory(),
            'fecha_hora' => now()->toDateTimeString(),
            'canal' => 'presencial',
            'descripcion_demanda' => fake()->sentence(),
            'clasificacion' => ClasificacionSia::CompetenciaMunicipal,
            'informacion_prestada' => null,
            'urgencia' => null,
            'cita_id' => null,
            'prestaciones_identificadas' => null,
        ];
    }

    /**
     * Contacto de competencia municipal.
     */
    public function competenciaMunicipal(): static
    {
        return $this->state(['clasificacion' => ClasificacionSia::CompetenciaMunicipal]);
    }

    /**
     * Contacto de otra administración (sin urgencia requerida).
     */
    public function otraAdministracion(): static
    {
        return $this->state([
            'clasificacion' => ClasificacionSia::OtraAdministracion,
            'urgencia' => null,
            'informacion_prestada' => fake()->paragraph(),
        ]);
    }
}
