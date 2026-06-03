<?php

namespace Modules\Intervencion\Database\Factories;

use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Intervencion\Enums\EstadoValoracion;
use Modules\Intervencion\Models\TipoValoracion;
use Modules\Intervencion\Models\Valoracion;

/**
 * @extends Factory<Valoracion>
 */
class ValoracionFactory extends Factory
{
    protected $model = Valoracion::class;

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
            'entrevista_id' => null,
            'profesional_id' => User::factory(),
            'tipo_valoracion_id' => TipoValoracion::factory(),
            'fecha' => today()->toDateString(),
            'estado' => EstadoValoracion::Borrador,
            'resumen' => null,
        ];
    }
}
