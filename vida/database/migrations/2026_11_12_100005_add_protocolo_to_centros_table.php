<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protocolo propio del centro (docs/modulo-tipo-centro.md §3): opcional;
 * si no lo tiene, rige el del tipo (propiedad sobrescribible).
 */
return new class extends Migration
{
    /**
     * Añade la columna.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            $table->text('protocolo')->nullable()->after('notas');
        });
    }

    /**
     * Elimina la columna.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            $table->dropColumn('protocolo');
        });
    }
};
