<?php

namespace Modules\Intervencion\Database\Factories;

use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Intervencion\Enums\TipoEntrevista;
use Modules\Intervencion\Models\Entrevista;

/**
 * @extends Factory<Entrevista>
 */
class EntrevistaFactory extends Factory
{
    protected $model = Entrevista::class;

    public function definition(): array
    {
        $uo = UnidadOrganizativa::firstOrCreate(
            ['nombre' => 'UO Test Factory Intervencion'],
            ['tipo' => 'centro', 'parent_id' => null, 'activa' => true]
        );

        $historia = HistoriaSocial::create([
            'ciudadano_id' => fake()->numberBetween(1, 9999),
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        return [
            'historia_id' => $historia->id,
            'profesional_id' => User::factory(),
            'cita_id' => null,
            'plan_intervencion_id' => null,
            'fecha_hora' => now()->toDateTimeString(),
            'modalidad' => 'presencial',
            'tipo' => TipoEntrevista::Inicial,
            'notas_generales' => null,
            'estado' => 'realizada',
        ];
    }

    /**
     * Entrevista de tipo seguimiento.
     */
    public function seguimiento(): static
    {
        return $this->state(['tipo' => TipoEntrevista::Seguimiento]);
    }

    /**
     * Entrevista de tipo informativa.
     */
    public function informativa(): static
    {
        return $this->state(['tipo' => TipoEntrevista::Informativa]);
    }
}
