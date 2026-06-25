<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla pivot que relaciona actividades con los profesionales responsables de coordinarlas.
 *
 * Un profesional puede ser coordinador de varias actividades.
 * La dirección de cada sesión concreta se gestiona en sesion_actividad_profesional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividad_profesional', function (Blueprint $table) {
            $table->foreignId('actividad_id')
                ->constrained('actividades')
                ->cascadeOnDelete();
            $table->foreignId('profesional_id')
                ->constrained('profesionales')
                ->cascadeOnDelete();
            $table->primary(['actividad_id', 'profesional_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividad_profesional');
    }
};
