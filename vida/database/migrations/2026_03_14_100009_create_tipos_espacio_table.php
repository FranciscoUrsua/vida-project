<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de tipos de espacio físico dentro de un centro.
 * Ej: dormitorio individual, dormitorio compartido, habitación adaptada...
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_espacio', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_espacio');
    }
};
