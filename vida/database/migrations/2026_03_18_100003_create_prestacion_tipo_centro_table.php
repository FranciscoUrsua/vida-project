<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relación entre prestaciones y tipos de centro que pueden ofrecerlas.
 *
 * `tipo_centro` es una clave de catalogos_sistema (grupo: 'centro.tipo'),
 * no una FK a la tabla centros. Registra qué TIPOS de centro pueden prestar
 * cada prestación, no qué centros concretos.
 *
 * El módulo Centros consume esta tabla para filtrar prestaciones disponibles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestacion_tipo_centro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestacion_id')->constrained('prestaciones')->cascadeOnDelete();
            $table->string('tipo_centro', 100); // clave de catalogos_sistema grupo 'centro.tipo'
            $table->timestamps();

            $table->unique(['prestacion_id', 'tipo_centro']);
            $table->index('tipo_centro');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestacion_tipo_centro');
    }
};
