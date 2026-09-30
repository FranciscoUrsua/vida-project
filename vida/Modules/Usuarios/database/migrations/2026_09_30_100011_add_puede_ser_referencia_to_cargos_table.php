<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marca qué cargos entran en el reparto de profesionales de referencia
 * (docs/modulo-asignacion.md §4.1). En la v1, solo Trabajador/a Social
 * (slug `ts`, estable según CargosSeeder).
 */
return new class extends Migration
{
    /**
     * Añade la columna y la activa para el cargo `ts`.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('cargos', function (Blueprint $table) {
            $table->boolean('puede_ser_referencia')->default(false);
        });

        DB::table('cargos')->where('slug', 'ts')->update(['puede_ser_referencia' => true]);
    }

    /**
     * Elimina la columna.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('cargos', function (Blueprint $table) {
            $table->dropColumn('puede_ser_referencia');
        });
    }
};
