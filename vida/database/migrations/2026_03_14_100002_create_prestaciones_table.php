<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de prestaciones sociales que pueden ofrecerse en un centro.
 * Stub mínimo para soportar la relación centro_prestacion.
 * La implementación completa corresponde al módulo de Prestaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestaciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200);
            $table->string('codigo', 30)->nullable()->unique()->comment('Código interno de la prestación');
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestaciones');
    }
};
