<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade la columna `valor` a catalogos_sistema para almacenar
 * datos de configuración estructurados (JSON) además de las
 * etiquetas textuales habituales.
 *
 * Se usa, entre otros, para el horario laboral por defecto
 * del módulo de Mensajes (clave `horario_laboral_defecto`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogos_sistema', function (Blueprint $table) {
            $table->text('valor')->nullable()->after('etiqueta')
                ->comment('Valor de configuración, tipicamente JSON para parámetros estructurados');
        });
    }

    public function down(): void
    {
        Schema::table('catalogos_sistema', function (Blueprint $table) {
            $table->dropColumn('valor');
        });
    }
};
