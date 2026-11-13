<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo del tipo de centro: público destinatario, tipos de plan y fichas
 * de valoración (docs/modulo-tipo-centro.md §2.1 y §6.2).
 *
 * `tipo_centro_tipo_ficha` sin filas para un tipo significa «todas las fichas».
 */
return new class extends Migration
{
    /**
     * Crea los tres pivotes del tipo.
     */
    public function up(): void
    {
        Schema::create('tipo_centro_segmento_poblacion', function (Blueprint $table) {
            $table->foreignId('tipo_centro_id')->constrained('tipos_centro')->cascadeOnDelete();
            $table->foreignId('segmento_poblacion_id')->constrained('segmentos_poblacion')->cascadeOnDelete();
            $table->primary(['tipo_centro_id', 'segmento_poblacion_id']);
        });

        Schema::create('tipo_centro_tipo_plan', function (Blueprint $table) {
            $table->foreignId('tipo_centro_id')->constrained('tipos_centro')->cascadeOnDelete();
            $table->foreignId('tipo_plan_id')->constrained('tipos_plan')->cascadeOnDelete();
            $table->primary(['tipo_centro_id', 'tipo_plan_id']);
        });

        Schema::create('tipo_centro_tipo_ficha', function (Blueprint $table) {
            $table->foreignId('tipo_centro_id')->constrained('tipos_centro')->cascadeOnDelete();
            $table->foreignId('tipo_ficha_id')->constrained('tipo_fichas')->cascadeOnDelete();
            $table->primary(['tipo_centro_id', 'tipo_ficha_id']);
        });
    }

    /**
     * Elimina los pivotes.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_centro_tipo_ficha');
        Schema::dropIfExists('tipo_centro_tipo_plan');
        Schema::dropIfExists('tipo_centro_segmento_poblacion');
    }
};
