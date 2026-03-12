<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: tabla de zonas (subdivisiones del distrito).
 *
 * Una zona es una agrupación configurable de unidades censales
 * dentro de un distrito. Permite distribuir la carga de trabajo
 * entre trabajadores sociales del mismo centro.
 *
 * @see docs/glosario.md § Zona
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zonas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->foreignId('distrito_id')
                ->constrained('distritos')
                ->onDelete('restrict')
                ->comment('Distrito al que pertenece esta zona');
            $table->text('descripcion')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index('distrito_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zonas');
    }
};
