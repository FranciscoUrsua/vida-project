<?php

namespace Database\Factories;

use App\Models\AccesoProtegido;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Factory para AccesoProtegido.
 *
 * @extends Factory<AccesoProtegido>
 */
class AccesoProtegidoFactory extends Factory
{
    /** @var class-string<AccesoProtegido> */
    protected $model = AccesoProtegido::class;

    /**
     * Estado por defecto: solicitud pendiente.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'usuario_id'          => User::factory(),
            'ciudadano_id'        => fake()->numberBetween(1, 99999),
            'solicitante_id'      => User::factory(),
            'justificacion'       => 'Coordinación por caso urgente',
            'estado'              => 'pendiente',
            'aprobado_por'        => null,
            'fecha_resolucion'    => null,
            'acceso_valido_hasta' => null,
        ];
    }

    /**
     * Solicitud aprobada con acceso vigente.
     *
     * @return static
     */
    public function aprobado(): static
    {
        return $this->state([
            'estado'              => 'aprobado',
            'aprobado_por'        => User::factory(),
            'fecha_resolucion'    => now()->subHour(),
            'acceso_valido_hasta' => Carbon::tomorrow(),
        ]);
    }

    /**
     * Solicitud aprobada pero con acceso expirado.
     *
     * @return static
     */
    public function aprobadoExpirado(): static
    {
        return $this->state([
            'estado'              => 'aprobado',
            'aprobado_por'        => User::factory(),
            'fecha_resolucion'    => Carbon::yesterday()->subDays(5),
            'acceso_valido_hasta' => Carbon::yesterday(),
        ]);
    }

    /**
     * Solicitud denegada.
     *
     * @return static
     */
    public function denegado(): static
    {
        return $this->state([
            'estado'           => 'denegado',
            'aprobado_por'     => User::factory(),
            'fecha_resolucion' => now()->subHour(),
        ]);
    }
}
