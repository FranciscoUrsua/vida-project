<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: retira la tabla de zonas.
 *
 * Las zonas (agrupaciones configurables de unidades censales dentro de un
 * distrito) quedan sustituidas por el catálogo oficial de barrios y secciones
 * censales (2026_09_30_100001 a 100003). Ninguna tabla la referenciaba y
 * estaba vacía en la base compartida al retirarla (2026-10-02).
 */
return new class extends Migration
{
    /**
     * Elimina la tabla de zonas.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::dropIfExists('zonas');
    }

    /**
     * Restaura la tabla con su estructura original, sin datos.
     *
     * @return void
     */
    public function down(): void
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
};
