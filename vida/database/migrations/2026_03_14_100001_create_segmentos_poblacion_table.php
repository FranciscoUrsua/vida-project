<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de segmentos de población a los que puede dirigirse un centro.
 * Ej: infancia, personas mayores, personas sin hogar, VVG, discapacidad...
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('segmentos_poblacion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segmentos_poblacion');
    }
};
