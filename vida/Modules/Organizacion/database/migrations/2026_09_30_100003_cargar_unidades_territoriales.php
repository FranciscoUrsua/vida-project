<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Organizacion\Database\Seeders\DistritosSeeder;
use Modules\Organizacion\Services\CargaUnidadesTerritoriales;

/**
 * Carga el catálogo territorial (barrios y secciones censales) en cada entorno.
 *
 * Es una migración de datos, no un seeder, para que el despliegue deje el
 * catálogo cargado sin pasos manuales: la asignación de centro por dirección
 * no funciona sin él. Si la tabla de distritos está vacía (entorno recién
 * creado), siembra antes los 21 distritos.
 *
 * @see docs/modulo-asignacion.md §2
 */
return new class extends Migration
{
    /**
     * Carga distritos (si faltan), barrios y secciones censales.
     *
     * @return void
     */
    public function up(): void
    {
        if (! DB::table('distritos')->exists()) {
            (new DistritosSeeder)->run();
        }

        $carga = new CargaUnidadesTerritoriales;
        $carga->cargarBarrios();
        $carga->cargarSecciones();
    }

    /**
     * Vacía el catálogo de barrios y secciones (los distritos se conservan).
     *
     * @return void
     */
    public function down(): void
    {
        DB::table('secciones_censales')->delete();
        DB::table('barrios')->delete();
    }
};
