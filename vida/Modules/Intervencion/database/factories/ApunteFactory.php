<?php

namespace Modules\Intervencion\Database\Factories;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Models\Apunte;

/**
 * @extends Factory<Apunte>
 */
class ApunteFactory extends Factory
{
    protected $model = Apunte::class;

    public function definition(): array
    {
        return [
            'historia_id'    => HistoriaSocial::factory(),
            'plan_id'        => null,
            'autor_id'       => User::factory(),
            'fecha'          => today()->toDateString(),
            'tipo'           => TipoApunte::Anotacion,
            'apuntable_type' => null,
            'apuntable_id'   => null,
            'contenido'      => fake()->sentence(),
            'visibilidad'    => VisibilidadApunte::Profesionales,
        ];
    }

    /**
     * Apunte privado (solo visible para el autor).
     */
    public function privado(): static
    {
        return $this->state(['visibilidad' => VisibilidadApunte::Privada]);
    }

    /**
     * Apunte de visibilidad profesionales.
     */
    public function paraProfesionales(): static
    {
        return $this->state(['visibilidad' => VisibilidadApunte::Profesionales]);
    }

    /**
     * Apunte visible también para el ciudadano.
     */
    public function paraCiudadano(): static
    {
        return $this->state(['visibilidad' => VisibilidadApunte::Ciudadano]);
    }
}
