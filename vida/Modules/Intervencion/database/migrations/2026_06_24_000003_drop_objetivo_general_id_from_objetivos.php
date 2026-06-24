<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina el campo objetivo_general_id de objetivos_catalogo y plan_objetivos.
 *
 * Los objetivos generales y específicos son tipos independientes; los específicos
 * pertenecen a un área temática (tipo_ficha_id), no a un objetivo general padre.
 * La FK auto-referencial era un error de diseño.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('objetivos_catalogo', function (Blueprint $table) {
            $table->dropForeign(['objetivo_general_id']);
            $table->dropColumn('objetivo_general_id');
        });

        Schema::table('plan_objetivos', function (Blueprint $table) {
            $table->dropForeign(['objetivo_general_id']);
            $table->dropColumn('objetivo_general_id');
        });
    }

    public function down(): void
    {
        Schema::table('objetivos_catalogo', function (Blueprint $table) {
            $table->foreignId('objetivo_general_id')
                ->nullable()
                ->constrained('objetivos_catalogo')
                ->nullOnDelete();
        });

        Schema::table('plan_objetivos', function (Blueprint $table) {
            $table->foreignId('objetivo_general_id')
                ->nullable()
                ->constrained('plan_objetivos')
                ->nullOnDelete();
        });
    }
};
