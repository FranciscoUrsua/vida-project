<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adapta la tabla fichas para el flujo directo de RegistrarValoracionPage.
 *
 * - Añade historia_id (nullable FK) para vincular fichas directamente a la historia
 *   cuando aún no existe una Valoracion formal.
 * - Hace valoracion_id nullable para no requerir una Valoracion previa.
 *   La vinculación completa (Ficha → Valoracion) queda pendiente (TODO).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fichas', function (Blueprint $table) {
            $table->unsignedBigInteger('historia_id')
                ->nullable()
                ->after('id');

            $table->foreign('historia_id')
                ->references('id')
                ->on('historias_sociales')
                ->onDelete('restrict');

            // valoracion_id pasa a nullable: la ficha puede existir sin valoracion formal
            $table->foreignId('valoracion_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('fichas', function (Blueprint $table) {
            $table->dropForeign(['historia_id']);
            $table->dropColumn('historia_id');
            $table->foreignId('valoracion_id')->nullable(false)->change();
        });
    }
};
