<?php

namespace Modules\Agenda\Database\Factories;

use App\Models\Ciudadano;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\OrigenCita;
use Modules\Agenda\Models\Cita;

/** @extends Factory<Cita> */
class CitaFactory extends Factory
{
    protected $model = Cita::class;

    public function definition(): array
    {
        $slot = SlotFactory::new()->reservado()->create();

        return [
            'slot_id' => $slot->id,
            'ciudadano_id' => fn () => Ciudadano::factory()->create()->id,
            'profesional_id' => $slot->usuario_id,
            'tipo_slot_id' => $slot->tipo_slot_id,
            'centro_id' => $slot->centro_id,
            'fecha' => $slot->fecha,
            'hora_inicio' => $slot->hora_inicio,
            'hora_fin' => $slot->hora_fin,
            'estado' => EstadoCita::Confirmada->value,
            'origen' => OrigenCita::Interno->value,
            'referencia_externa' => null,
            'creado_por_id' => null,
            'cancelado_por_id' => null,
            'motivo_cancelacion' => null,
            'completada_en' => null,
        ];
    }

    public function externa(): static
    {
        return $this->state([
            'origen' => OrigenCita::ApiExterna->value,
            'referencia_externa' => 'REF-EXT-'.fake()->numerify('####'),
        ]);
    }

    public function completada(): static
    {
        return $this->state([
            'estado' => EstadoCita::Completada->value,
            'completada_en' => now(),
        ]);
    }

    public function cancelada(): static
    {
        return $this->state(['estado' => EstadoCita::Cancelada->value]);
    }

    public function noShowCiudadano(): static
    {
        return $this->state(['estado' => EstadoCita::NoShowCiudadano->value]);
    }

    public function noShowProfesional(): static
    {
        return $this->state(['estado' => EstadoCita::NoShowProfesional->value]);
    }

    public function pasada(): static
    {
        return $this->state(['fecha' => now()->subDay()->toDateString()]);
    }
}
