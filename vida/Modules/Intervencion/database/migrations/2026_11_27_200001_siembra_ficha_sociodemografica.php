<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Intervencion\Database\Seeders\FichaSociodemograficaSeeder;

/**
 * Siembra la ficha inicial «Datos sociodemográficos» en cada entorno al desplegar (los seeders
 * no se ejecutan al desplegar, decisiones técnicas §16). Idempotente: si ya existe un tipo con
 * `codigo = sociodemografica`, no lo toca.
 *
 * Depende de los catálogos oficiales (`2026_11_26_200002`): sus campos de catálogo exigen grupos
 * con valores vigentes.
 *
 * @see docs/instrucciones-cli/2026-11-ficha-sociodemografica.md, bloque E
 */
return new class extends Migration
{
    /**
     * Crea el tipo de ficha si no existe.
     *
     * @return void
     */
    public function up(): void
    {
        (new FichaSociodemograficaSeeder)->run();
    }

    /**
     * Retira el tipo solo si no tiene fichas (en cuanto las tiene, no debe deshacerse).
     *
     * @return void
     */
    public function down(): void
    {
        $id = DB::table('tipo_fichas')->where('codigo', FichaSociodemograficaSeeder::CODIGO)->value('id');
        if ($id !== null && ! DB::table('fichas')->where('tipo_ficha_id', $id)->exists()) {
            DB::table('tipo_fichas')->where('id', $id)->delete();
        }
    }
};
