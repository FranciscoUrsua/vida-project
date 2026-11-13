<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motivo de la resolución de una solicitud de acceso a persona protegida
 * (obligatorio al denegar, RN-18 de docs/modulo-tipo-centro.md). Solo añade
 * una columna nullable: no toca filas existentes.
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
        Schema::table('accesos_protegidos', function (Blueprint $table) {
            $table->text('motivo_resolucion')->nullable()->after('fecha_resolucion')
                ->comment('Motivo de la resolución; obligatorio al denegar (RN-18)');
        });
    }

    /**
     * Quita la columna.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('accesos_protegidos', function (Blueprint $table) {
            $table->dropColumn('motivo_resolucion');
        });
    }
};
