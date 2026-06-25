<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade unidad_organizativa_id a profesionales.
 *
 * Permite vincular directamente un profesional a su UO incluso antes de
 * que tenga cuenta de usuario (usuario_id = null). Necesario para el
 * flujo de alta de profesional desde el módulo Supervisión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profesionales', function (Blueprint $table) {
            $table->foreignId('unidad_organizativa_id')
                ->nullable()
                ->after('activo')
                ->constrained('unidades_organizativas')
                ->onDelete('restrict')
                ->comment('UO a la que el profesional está adscrito directamente (antes de tener cuenta de usuario)');
        });
    }

    public function down(): void
    {
        Schema::table('profesionales', function (Blueprint $table) {
            $table->dropForeign(['unidad_organizativa_id']);
            $table->dropColumn('unidad_organizativa_id');
        });
    }
};
