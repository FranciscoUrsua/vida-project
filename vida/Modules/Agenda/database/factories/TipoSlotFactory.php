<?php

namespace Modules\Agenda\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Agenda\Enums\OrigenPermitidoSlot;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\TipoSlot;

/** @extends Factory<TipoSlot> */
class TipoSlotFactory extends Factory
{
    protected $model = TipoSlot::class;

    public function definition(): array
    {
        return [
            'horario_centro_id' => HorarioCentroFactory::new(),
            'nombre' => 'Atención general',
            'descripcion' => null,
            'duracion_minutos' => 45,
            'requiere_espacio' => false,
            'porcentaje_urgencias' => 0,
            'origen_permitido' => OrigenPermitidoSlot::Ambos->value,
            'genera_apunte_automatico' => false,
            'activo' => true,
        ];
    }

    public function conUrgencias(int $porcentaje): static
    {
        return $this->state(['porcentaje_urgencias' => $porcentaje]);
    }

    public function soloInterno(): static
    {
        return $this->state(['origen_permitido' => OrigenPermitidoSlot::Interno->value]);
    }

    public function generaApunte(): static
    {
        return $this->state(['genera_apunte_automatico' => true]);
    }

    public function paraCentro(int $centroId): static
    {
        $horario = HorarioCentro::factory()->create(['centro_id' => $centroId]);

        return $this->state(['horario_centro_id' => $horario->id]);
    }
}
