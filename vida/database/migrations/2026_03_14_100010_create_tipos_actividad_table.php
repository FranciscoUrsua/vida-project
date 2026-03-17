<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de tipos de actividad que puede ofrecer un centro.
 * Ej: taller de empleo, grupo terapéutico, actividad deportiva...
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_actividad', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_actividad');
    }
};
