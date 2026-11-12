<?php

namespace Modules\Centro\Database\Factories;

use App\Models\UnidadOrganizativa;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Centro\Models\TipoCentro;

/**
 * Factoría de tipos de centro para tests: código único y UO funcional propia.
 *
 * @extends Factory<TipoCentro>
 */
class TipoCentroFactory extends Factory
{
    /** @var class-string<TipoCentro> */
    protected $model = TipoCentro::class;

    /**
     * Tipo activo con la configuración por defecto.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $codigo = 'tipo_'.fake()->unique()->numerify('#####');

        return [
            'codigo' => $codigo,
            'nombre' => 'Tipo '.$codigo,
            'siglas' => mb_strtoupper(mb_substr($codigo, 0, 20)),
            'unidad_organizativa_id' => fn () => UnidadOrganizativa::create([
                'nombre' => 'UO funcional '.$codigo,
                'tipo' => 'departamento',
                'parent_id' => null,
                'activa' => true,
            ])->id,
            'fecha_alta' => '2020-01-01',
        ];
    }
}
