<?php

use App\Services\Catalogos\CargaCatalogosOficiales;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Carga los catálogos comunes desde las fuentes oficiales en cada entorno al desplegar
 * (los seeders no se ejecutan al desplegar, decisiones técnicas §16).
 *
 * La carga es idempotente: si los catálogos ya existen, solo actualiza lo que haya cambiado.
 *
 * @see docs/instrucciones-cli/2026-11-ficha-sociodemografica.md, bloque A
 */
return new class extends Migration
{
    /**
     * Carga países, niveles de estudios, situaciones laborales e idiomas.
     *
     * @return void
     */
    public function up(): void
    {
        (new CargaCatalogosOficiales)->todos();
    }

    /**
     * Retira los catálogos comunes cargados.
     *
     * Solo para deshacer en desarrollo: en cuanto haya fichas que guarden sus claves no
     * debe deshacerse (las fichas perderían la etiqueta).
     *
     * @return void
     */
    public function down(): void
    {
        DB::table('catalogos_sistema')->whereIn('grupo', array_keys(CargaCatalogosOficiales::FICHEROS))->delete();
    }
};
