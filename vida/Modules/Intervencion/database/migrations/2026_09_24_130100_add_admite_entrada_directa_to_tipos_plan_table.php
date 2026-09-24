<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: tipos de plan que admiten entrada directa (sin plan ASP previo).
 *
 * El CIAM es una puerta alternativa de entrada al sistema: un plan especializado
 * de un tipo con admite_entrada_directa = true puede crearse con plan_asp_id = null.
 * Para el resto de tipos se mantiene la regla de que el plan especializado nace
 * de una derivación desde un plan ASP.
 *
 * Marca solo el tipo 'pia' (si existe) y solo en este campo: no toca updated_at
 * ni ningún otro dato del tipo.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tipos_plan', function (Blueprint $table) {
            $table->boolean('admite_entrada_directa')->default(false)->after('ambito');
        });

        // Query builder: no actualiza updated_at (solo se cambia este campo).
        DB::table('tipos_plan')
            ->where('slug', 'pia')
            ->update(['admite_entrada_directa' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tipos_plan', function (Blueprint $table) {
            $table->dropColumn('admite_entrada_directa');
        });
    }
};
