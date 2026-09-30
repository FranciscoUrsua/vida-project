<?php

namespace Modules\Agenda\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Agenda\Enums\OrigenPermitidoSlot;
use Modules\Agenda\Models\TipoSlot;

/**
 * Factoría de tipos de slot. El tipo de slot es un catálogo global: no
 * pertenece a ningún horario de centro.
 *
 * @extends Factory<TipoSlot>
 */
class TipoSlotFactory extends Factory
{
    protected $model = TipoSlot::class;

    /**
     * Tipo de slot genérico de atención, activo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Atención general',
            'descripcion' => null,
            'duracion_minutos' => 45,
            'requiere_espacio' => false,
            'porcentaje_urgencias' => 0,
            'origen_permitido' => OrigenPermitidoSlot::Ambos->value,
            'activo' => true,
        ];
    }

    /**
     * Reserva un porcentaje de los slots para urgencias.
     *
     * @param int $porcentaje
     * @return static
     */
    public function conUrgencias(int $porcentaje): static
    {
        return $this->state(['porcentaje_urgencias' => $porcentaje]);
    }

    /**
     * Solo admite citas del canal interno.
     *
     * @return static
     */
    public function soloInterno(): static
    {
        return $this->state(['origen_permitido' => OrigenPermitidoSlot::Interno->value]);
    }
}
