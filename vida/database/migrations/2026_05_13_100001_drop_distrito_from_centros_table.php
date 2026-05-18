<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retira distrito_id de centros.
 *
 * unidad_organizativa_id ya existe desde la migración de creación.
 * El ámbito territorial se modela ahora a través de ambitos_territoriales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            if (Schema::hasColumn('centros', 'distrito_id')) {
                $table->dropForeign(['distrito_id']);
                $table->dropColumn('distrito_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            if (! Schema::hasColumn('centros', 'distrito_id')) {
                $table->unsignedBigInteger('distrito_id')->nullable()->after('codigo_postal');
                $table->foreign('distrito_id')
                    ->references('id')
                    ->on('distritos')
                    ->nullOnDelete();
            }
        });
    }
};
