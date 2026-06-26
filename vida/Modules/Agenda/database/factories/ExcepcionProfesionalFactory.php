<?php

namespace Modules\Agenda\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Agenda\Enums\OrigenExcepcion;
use Modules\Agenda\Enums\TipoExcepcion;
use Modules\Agenda\Models\ExcepcionProfesional;
use Modules\Centro\Models\Centro;

/** @extends Factory<ExcepcionProfesional> */
class ExcepcionProfesionalFactory extends Factory
{
    protected $model = ExcepcionProfesional::class;

    public function definition(): array
    {
        return [
            'usuario_id' => User::factory(),
            'centro_id' => fn () => Centro::create([
                'nombre' => 'Centro '.fake()->unique()->numerify('###'),
                'tipo_gestion' => 'municipal_directo',
                'fecha_alta' => now()->toDateString(),
            ])->id,
            'tipo' => TipoExcepcion::DiaLibre->value,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'afecta_disponibilidad' => true,
            'franja_afectada' => null,
            'origen' => OrigenExcepcion::Manual->value,
            'creado_por_id' => User::factory(),
            'notas' => null,
        ];
    }

    public function baja(): static
    {
        return $this->state(['tipo' => TipoExcepcion::BajaMedica->value]);
    }

    public function vacaciones(int $dias = 7): static
    {
        return $this->state([
            'tipo' => TipoExcepcion::Vacaciones->value,
            'fecha_fin' => now()->addDays($dias)->toDateString(),
        ]);
    }

    public function formacion(): static
    {
        return $this->state([
            'tipo' => TipoExcepcion::Formacion->value,
            'afecta_disponibilidad' => false,
        ]);
    }

    public function sinAfectar(): static
    {
        return $this->state(['afecta_disponibilidad' => false]);
    }
}
