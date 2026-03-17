<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla pivote: centros ↔ segmentos_poblacion.
 * Indica a qué colectivos atiende el centro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centro_segmento_poblacion', function (Blueprint $table) {
            $table->foreignId('centro_id')->constrained('centros')->cascadeOnDelete();
            $table->foreignId('segmento_poblacion_id')->constrained('segmentos_poblacion')->cascadeOnDelete();

            $table->primary(['centro_id', 'segmento_poblacion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centro_segmento_poblacion');
    }
};
