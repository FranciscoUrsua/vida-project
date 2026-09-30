<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Umbral de ausencia prolongada del centro, en días.
 *
 * Un profesional con una ausencia registrada más larga que este umbral no
 * entra en el sorteo de referencias mientras dure (docs/modulo-asignacion.md
 * RN-07). Lo comparte la fase de Citas para decidir cuándo se buscan sustitutos.
 */
return new class extends Migration
{
    /**
     * Añade la columna con valor por defecto 15.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('horarios_centro', function (Blueprint $table) {
            $table->unsignedSmallInteger('dias_ausencia_prolongada')->default(15);
        });
    }

    /**
     * Elimina la columna.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('horarios_centro', function (Blueprint $table) {
            $table->dropColumn('dias_ausencia_prolongada');
        });
    }
};
