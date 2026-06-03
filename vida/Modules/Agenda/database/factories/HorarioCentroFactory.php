<?php

namespace Modules\Agenda\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Agenda\Enums\ModoAgenda;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Centro\Models\Centro;

class HorarioCentroFactory extends Factory
{
    protected $model = HorarioCentro::class;

    public function definition(): array
    {
        return [
            'centro_id' => fn () => Centro::create([
                'nombre' => 'Centro '.fake()->unique()->numerify('###'),
                'tipo_gestion' => 'municipal_directo',
                'fecha_alta' => now()->toDateString(),
            ])->id,
            'nombre' => 'Horario estándar',
            'dias_laborables' => [1, 2, 3, 4, 5],
            'hora_apertura' => '08:00',
            'hora_cierre' => '19:00',
            'hora_inicio_atencion' => '09:00',
            'hora_fin_atencion' => '14:00',
            'buffer_inicio_minutos' => 0,
            'buffer_fin_minutos' => 0,
            'vigente_desde' => now()->toDateString(),
            'vigente_hasta' => null,
            'modo_agenda' => ModoAgenda::Estandar->value,
            'activo' => true,
        ];
    }

    public function basico(): static
    {
        return $this->state(['modo_agenda' => ModoAgenda::Basico->value]);
    }

    public function avanzado(): static
    {
        return $this->state(['modo_agenda' => ModoAgenda::Avanzado->value]);
    }

    public function conBuffer(int $minutos): static
    {
        return $this->state(['buffer_inicio_minutos' => $minutos]);
    }

    public function verano(): static
    {
        return $this->state([
            'nombre' => 'Horario verano',
            'hora_inicio_atencion' => '08:00',
            'hora_fin_atencion' => '15:00',
        ]);
    }
}
