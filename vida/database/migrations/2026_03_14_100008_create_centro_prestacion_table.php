<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla pivote: centros ↔ prestaciones.
 * Indica qué prestaciones ofrece cada centro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centro_prestacion', function (Blueprint $table) {
            $table->foreignId('centro_id')->constrained('centros')->cascadeOnDelete();
            $table->foreignId('prestacion_id')->constrained('prestaciones')->cascadeOnDelete();

            $table->primary(['centro_id', 'prestacion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centro_prestacion');
    }
};
