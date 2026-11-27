<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Código estable del tipo de ficha (SD-6 de la ficha sociodemográfica): identificador para
 * la analítica y para el código, inmutable en cuanto el tipo tiene fichas. Nulo en los tipos
 * existentes.
 *
 * @see docs/instrucciones-cli/2026-11-ficha-sociodemografica.md, bloque B
 */
return new class extends Migration
{
    /**
     * Añade la columna `codigo` (única, nullable).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('tipo_fichas', function (Blueprint $table) {
            $table->string('codigo', 60)->nullable()->unique()->after('id')
                ->comment('Identificador estable (analítica y código); inmutable con fichas');
        });
    }

    /**
     * Retira la columna.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('tipo_fichas', function (Blueprint $table) {
            $table->dropColumn('codigo');
        });
    }
};
