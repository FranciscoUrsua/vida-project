<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogos versionados (SD-1 de la ficha sociodemográfica): vigencia, agrupaciones,
 * códigos equivalentes en otros sistemas y fuente oficial de cada valor.
 *
 * Las filas existentes quedan con todo nulo: sin vigencia significa vigente siempre.
 *
 * @see docs/instrucciones-cli/2026-11-ficha-sociodemografica.md, bloque A
 */
return new class extends Migration
{
    /**
     * Añade las columnas de versionado y equivalencias.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('catalogos_sistema', function (Blueprint $table) {
            $table->date('vigente_desde')->nullable()->after('activo');
            $table->date('vigente_hasta')->nullable()->after('vigente_desde')
                ->comment('Último día vigente (inclusive); nulo = sin fin');
            $table->jsonb('atributos')->nullable()->after('vigente_hasta')
                ->comment('Agrupaciones del valor (región, UE, nivel agregado…)');
            $table->jsonb('equivalencias')->nullable()->after('atributos')
                ->comment('Códigos del valor en otros sistemas (iso2, m49, ine, hsu…)');
            $table->string('fuente')->nullable()->after('equivalencias')
                ->comment('Fuente oficial y versión del fichero del que sale el valor');
        });
    }

    /**
     * Retira las columnas.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('catalogos_sistema', function (Blueprint $table) {
            $table->dropColumn(['vigente_desde', 'vigente_hasta', 'atributos', 'equivalencias', 'fuente']);
        });
    }
};
