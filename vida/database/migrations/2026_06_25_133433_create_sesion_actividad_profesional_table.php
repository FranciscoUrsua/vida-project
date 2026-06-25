<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla pivot que relaciona sesiones concretas de actividad con los profesionales que las dirigen.
 *
 * Permite que cada sesión tenga un conjunto de profesionales distinto al de la actividad,
 * cubriendo el caso de rotación de profesionales semana a semana.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesion_actividad_profesional', function (Blueprint $table) {
            $table->foreignId('sesion_actividad_id')
                ->constrained('sesiones_actividad')
                ->cascadeOnDelete();
            $table->foreignId('profesional_id')
                ->constrained('profesionales')
                ->cascadeOnDelete();
            $table->primary(['sesion_actividad_id', 'profesional_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesion_actividad_profesional');
    }
};
