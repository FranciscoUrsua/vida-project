<?php

namespace Modules\Organizacion\Database\Seeders;

/**
 * Datos de ejemplo basados en la estructura del Ayuntamiento de Madrid.
 * Deben adaptarse a la organización que despliega VIDA 360.
 */

use Illuminate\Database\Seeder;
use Modules\Organizacion\Models\Distrito;

/**
 * Seeder de los 21 distritos del municipio de Madrid.
 *
 * Datos basados en el Reglamento Orgánico 6/2021 del Ayuntamiento de Madrid.
 * Las coordenadas son aproximadas (centroides de cada distrito).
 */
class DistritosSeeder extends Seeder
{
    /**
     * Los 21 distritos de Madrid con sus códigos y coordenadas aproximadas.
     *
     * @var array<int, array{nombre: string, codigo: string, latitud: float, longitud: float}>
     */
    private const DISTRITOS = [
        ['nombre' => 'Centro',             'codigo' => '01', 'latitud' => 40.4130, 'longitud' => -3.7034],
        ['nombre' => 'Arganzuela',         'codigo' => '02', 'latitud' => 40.3988, 'longitud' => -3.6963],
        ['nombre' => 'Retiro',             'codigo' => '03', 'latitud' => 40.4105, 'longitud' => -3.6783],
        ['nombre' => 'Salamanca',          'codigo' => '04', 'latitud' => 40.4298, 'longitud' => -3.6764],
        ['nombre' => 'Chamartín',          'codigo' => '05', 'latitud' => 40.4607, 'longitud' => -3.6768],
        ['nombre' => 'Tetuán',             'codigo' => '06', 'latitud' => 40.4617, 'longitud' => -3.7017],
        ['nombre' => 'Chamberí',           'codigo' => '07', 'latitud' => 40.4404, 'longitud' => -3.7004],
        ['nombre' => 'Fuencarral-El Pardo', 'codigo' => '08', 'latitud' => 40.5052, 'longitud' => -3.7248],
        ['nombre' => 'Moncloa-Aravaca',    'codigo' => '09', 'latitud' => 40.4366, 'longitud' => -3.7392],
        ['nombre' => 'Latina',             'codigo' => '10', 'latitud' => 40.4053, 'longitud' => -3.7464],
        ['nombre' => 'Carabanchel',        'codigo' => '11', 'latitud' => 40.3835, 'longitud' => -3.7417],
        ['nombre' => 'Usera',              'codigo' => '12', 'latitud' => 40.3900, 'longitud' => -3.7126],
        ['nombre' => 'Puente de Vallecas', 'codigo' => '13', 'latitud' => 40.3897, 'longitud' => -3.6698],
        ['nombre' => 'Moratalaz',          'codigo' => '14', 'latitud' => 40.4064, 'longitud' => -3.6486],
        ['nombre' => 'Ciudad Lineal',      'codigo' => '15', 'latitud' => 40.4452, 'longitud' => -3.6583],
        ['nombre' => 'Hortaleza',          'codigo' => '16', 'latitud' => 40.4750, 'longitud' => -3.6396],
        ['nombre' => 'Villaverde',         'codigo' => '17', 'latitud' => 40.3551, 'longitud' => -3.6977],
        ['nombre' => 'Villa de Vallecas',  'codigo' => '18', 'latitud' => 40.3729, 'longitud' => -3.6235],
        ['nombre' => 'Vicálvaro',          'codigo' => '19', 'latitud' => 40.4052, 'longitud' => -3.6055],
        ['nombre' => 'San Blas-Canillejas', 'codigo' => '20', 'latitud' => 40.4391, 'longitud' => -3.6152],
        ['nombre' => 'Barajas',            'codigo' => '21', 'latitud' => 40.4791, 'longitud' => -3.5792],
    ];

    /**
     * Crea los 21 distritos de Madrid si no existen.
     */
    public function run(): void
    {
        foreach (self::DISTRITOS as $datos) {
            Distrito::firstOrCreate(
                ['codigo' => $datos['codigo']],
                array_merge($datos, ['activo' => true])
            );
        }

        $this->command->info('✓ '.count(self::DISTRITOS).' distritos de Madrid creados.');
    }
}
