<?php

namespace Modules\Agenda\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Agenda\Models\EventoAgenda;
use Modules\Centro\Models\Centro;

/** @extends Factory<EventoAgenda> */
class EventoAgendaFactory extends Factory
{
    protected $model = EventoAgenda::class;

    public function definition(): array
    {
        return [
            'centro_id' => fn () => Centro::create([
                'nombre' => 'Centro '.fake()->unique()->numerify('###'),
                'tipo_gestion' => 'municipal_directo',
                'fecha_alta' => now()->toDateString(),
            ])->id,
            'tipo_evento' => 'reunion_equipo',
            'titulo' => 'Reunión de equipo',
            'descripcion' => null,
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'espacio_id' => null,
            'creado_por_id' => User::factory(),
        ];
    }
}
