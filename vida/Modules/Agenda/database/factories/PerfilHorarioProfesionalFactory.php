<?php

namespace Modules\Agenda\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Centro\Models\Centro;

class PerfilHorarioProfesionalFactory extends Factory
{
    protected $model = PerfilHorarioProfesional::class;

    public function definition(): array
    {
        return [
            'usuario_id' => User::factory(),
            'centro_id'  => fn () => Centro::create([
                'nombre'       => 'Centro ' . fake()->unique()->numerify('###'),
                'tipo_gestion' => 'municipal_directo',
                'fecha_alta'   => now()->toDateString(),
            ])->id,
            'jornada_semanal_horas' => 35,
            'horario_habitual'      => [
                1 => [['inicio' => '09:00', 'fin' => '14:00'], ['inicio' => '15:00', 'fin' => '19:00']],
                2 => [['inicio' => '09:00', 'fin' => '14:00'], ['inicio' => '15:00', 'fin' => '19:00']],
                3 => [['inicio' => '09:00', 'fin' => '14:00'], ['inicio' => '15:00', 'fin' => '19:00']],
                4 => [['inicio' => '09:00', 'fin' => '14:00'], ['inicio' => '15:00', 'fin' => '19:00']],
                5 => [['inicio' => '09:00', 'fin' => '14:00'], ['inicio' => '15:00', 'fin' => '19:00']],
            ],
            'vigente_desde' => now()->toDateString(),
            'vigente_hasta' => null,
            'activo'        => true,
        ];
    }

    public function reducido(): static
    {
        return $this->state([
            'jornada_semanal_horas' => 17.5,
            'horario_habitual'      => [
                1 => [['inicio' => '09:30', 'fin' => '14:00']],
                2 => [['inicio' => '09:30', 'fin' => '14:00']],
                3 => [['inicio' => '09:30', 'fin' => '14:00']],
                4 => [['inicio' => '09:30', 'fin' => '14:00']],
                5 => [['inicio' => '09:30', 'fin' => '14:00']],
            ],
        ]);
    }

    public function matutino(): static
    {
        return $this->state([
            'horario_habitual' => [
                1 => [['inicio' => '09:00', 'fin' => '14:00']],
                2 => [['inicio' => '09:00', 'fin' => '14:00']],
                3 => [['inicio' => '09:00', 'fin' => '14:00']],
                4 => [['inicio' => '09:00', 'fin' => '14:00']],
                5 => [['inicio' => '09:00', 'fin' => '14:00']],
            ],
        ]);
    }

    public function paraCentro(\Modules\Centro\Models\Centro $centro): static
    {
        return $this->state(['centro_id' => $centro->id]);
    }
}
