<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade criterio_territorial a colecciones_plazas.
 *
 * Cuando es true, las opciones de destino se ordenan por proximidad al domicilio
 * del ciudadano al prescribir el recurso (heurística §2 del flujo de plazas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('colecciones_plazas', function (Blueprint $table) {
            $table->boolean('criterio_territorial')->default(false)->after('modo_acceso');
        });
    }

    public function down(): void
    {
        Schema::table('colecciones_plazas', function (Blueprint $table) {
            $table->dropColumn('criterio_territorial');
        });
    }
};
