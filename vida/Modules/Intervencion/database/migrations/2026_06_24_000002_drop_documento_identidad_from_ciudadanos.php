<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina el campo documento_identidad de ciudadanos.
 *
 * La fuente de verdad para documentos de identidad es la tabla
 * ciudadano_identificadores (historial versionado). El campo directo
 * en ciudadanos nunca se rellenaba, generaba confusión y duplicaba
 * el modelo de datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ciudadanos', function (Blueprint $table) {
            $table->dropColumn('documento_identidad');
        });
    }

    public function down(): void
    {
        Schema::table('ciudadanos', function (Blueprint $table) {
            $table->text('documento_identidad')->nullable()->after('email');
        });
    }
};
