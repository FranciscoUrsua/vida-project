<?php

namespace Modules\Agenda\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Models\LineaCuadrante;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Models\TipoSlot;
use Modules\Centro\Models\Centro;

class SlotFactory extends Factory
{
    protected $model = Slot::class;

    public function definition(): array
    {
        return [
            'linea_cuadrante_id' => LineaCuadranteFactory::new(),
            'usuario_id'         => User::factory(),
            'centro_id'          => fn () => Centro::create([
                'nombre'       => 'Centro ' . fake()->unique()->numerify('###'),
                'tipo_gestion' => 'municipal_directo',
                'fecha_alta'   => now()->toDateString(),
            ])->id,
            'tipo_slot_id'       => TipoSlotFactory::new(),
            'fecha'              => now()->addDay()->toDateString(),
            'hora_inicio'        => '10:00',
            'hora_fin'           => '10:45',
            'estado'             => EstadoSlot::Disponible->value,
            'espacio_id'         => null,
        ];
    }

    public function urgencia(): static
    {
        return $this->state(['estado' => EstadoSlot::BloqueadoUrgencia->value]);
    }

    public function reservado(): static
    {
        return $this->state(['estado' => EstadoSlot::Reservado->value]);
    }

    public function bloqueadoEvento(): static
    {
        return $this->state(['estado' => EstadoSlot::BloqueadoEvento->value]);
    }

    public function anulado(): static
    {
        return $this->state(['estado' => EstadoSlot::Anulado->value]);
    }

    public function expirado(): static
    {
        return $this->state([
            'estado' => EstadoSlot::Expirado->value,
            'fecha'  => now()->subDay()->toDateString(),
        ]);
    }

    public function noOcupado(): static
    {
        return $this->state([
            'estado' => EstadoSlot::NoOcupado->value,
            'fecha'  => now()->subDay()->toDateString(),
        ]);
    }
}
