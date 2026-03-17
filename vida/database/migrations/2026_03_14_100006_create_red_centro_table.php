<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla pivote: redes ↔ centros.
 * Sin id propio ni timestamps — relación pura many-to-many.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('red_centro', function (Blueprint $table) {
            $table->foreignId('red_id')->constrained('redes')->cascadeOnDelete();
            $table->foreignId('centro_id')->constrained('centros')->cascadeOnDelete();

            $table->primary(['red_id', 'centro_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('red_centro');
    }
};
