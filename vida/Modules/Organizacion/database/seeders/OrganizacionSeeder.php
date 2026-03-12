<?php

namespace Modules\Organizacion\Database\Seeders;

/**
 * Datos de ejemplo basados en la estructura del Ayuntamiento de Madrid.
 * Deben adaptarse a la organización que despliega VIDA 360.
 */

use Illuminate\Database\Seeder;

/**
 * Seeder orquestador del módulo Organizacion.
 *
 * Ejecuta todos los seeders del módulo en el orden correcto de dependencias.
 * Se llama desde DatabaseSeeder como punto de entrada único del módulo.
 */
class OrganizacionSeeder extends Seeder
{
    /**
     * Ejecuta los seeders del módulo Organizacion en orden.
     *
     * @return void
     */
    public function run(): void
    {
        $this->call([
            ConfiguracionSeeder::class,
            ColectivosProtegidosSeeder::class,
            ServiciosEmergenciaSeeder::class,
            DistritosSeeder::class,
        ]);
    }
}
