<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dropea si existe (seguro, no error si no)
        Schema::dropIfExists('centro_profesional');

        // Recrear con definición actual
        Schema::create('centro_profesional', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centro_id')->constrained('centros')->onDelete('cascade');
            $table->foreignId('profesional_id')->constrained('profesionales')->onDelete('cascade');
            $table->timestamps();  // Para fecha de asignación/baja
            $table->unique(['centro_id', 'profesional_id']);  // No duplicados (un pro en un centro)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centro_profesional');
    }
};
