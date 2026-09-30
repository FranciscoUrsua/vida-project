<?php

namespace Modules\Organizacion\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Organizacion\Services\CargaUnidadesTerritoriales;

/**
 * Seeder del catálogo territorial: barrios y secciones censales de Madrid.
 *
 * Idempotente: delega en CargaUnidadesTerritoriales, que actualiza por código.
 * Requiere los distritos ya sembrados (DistritosSeeder).
 */
class UnidadesTerritorialesSeeder extends Seeder
{
    /**
     * Carga barrios y secciones censales desde los ficheros oficiales.
     *
     * @param CargaUnidadesTerritoriales $carga
     * @return void
     */
    public function run(CargaUnidadesTerritoriales $carga): void
    {
        $barrios = $carga->cargarBarrios();
        $secciones = $carga->cargarSecciones();

        $this->command?->info("✓ {$barrios} barrios y {$secciones} secciones censales cargados.");
    }
}
