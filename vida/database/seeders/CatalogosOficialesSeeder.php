<?php

namespace Database\Seeders;

use App\Services\Catalogos\CargaCatalogosOficiales;
use Illuminate\Database\Seeder;

/**
 * Siembra los catálogos comunes (`comun.pais`, `comun.nivel_estudios`,
 * `comun.situacion_laboral`, `comun.idioma`) desde los CSV normalizados de las fuentes oficiales.
 *
 * Idempotente: delega en CargaCatalogosOficiales, que actualiza por clave sin duplicar,
 * sin reactivar lo desactivado a mano y sin borrar. En los entornos desplegados los carga
 * la migración de datos `2026_11_26_200002_cargar_catalogos_oficiales`.
 */
class CatalogosOficialesSeeder extends Seeder
{
    /**
     * Carga los cuatro catálogos e informa del recuento.
     *
     * @param CargaCatalogosOficiales $carga
     *
     * @return void
     */
    public function run(CargaCatalogosOficiales $carga): void
    {
        foreach ($carga->todos() as $resultado) {
            $this->command->info('✓ '.$resultado->resumen());
        }
    }
}
