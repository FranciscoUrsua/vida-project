<?php

namespace Modules\Atencion\Database\Factories;

use App\Models\Ciudadano;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Atencion\Models\RegistroAtencion;

/**
 * Factory para RegistroAtencion.
 *
 * Por defecto genera registros de tipo informacion con profesional asignado.
 * Usar estados actividad() o contacto() para otros tipos.
 *
 * @extends Factory<RegistroAtencion>
 */
class RegistroAtencionFactory extends Factory
{
    /** @var class-string<RegistroAtencion> */
    protected $model = RegistroAtencion::class;

    /**
     * Estado por defecto: atención de tipo información con profesional.
     *
     */
    public function definition(): array
    {
        return [
            'ciudadano_id' => Ciudadano::factory(),
            'tipo' => 'informacion',
            'fecha' => $this->faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'profesional_id' => User::factory(),
            'prestacion_id' => null,
            'demanda' => $this->faker->sentence(10),
            'respuesta' => $this->faker->sentence(8),
            'origen' => 'manual',
            'origen_tipo' => null,
            'origen_id' => null,
        ];
    }

    /**
     * Registro generado automáticamente por una inscripción en actividad.
     */
    public function actividad(): static
    {
        return $this->state(fn () => [
            'tipo' => 'actividad',
            'origen' => 'sistema',
            'origen_tipo' => 'Modules\\Centro\\Models\\Inscripcion',
            'origen_id' => $this->faker->randomNumber(3),
            'profesional_id' => null,
            'demanda' => null,
            'respuesta' => null,
        ]);
    }

    /**
     * Registro de contacto telefónico o informal.
     */
    public function contacto(): static
    {
        return $this->state(fn () => [
            'tipo' => 'contacto',
        ]);
    }
}
