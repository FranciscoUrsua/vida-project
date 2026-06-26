<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de auditoría de reordenamientos de lista de espera.
 *
 * Cada vez que un profesional cambia la posición de una prescripción en la lista,
 * se registra aquí la posición anterior, la nueva y quién realizó el cambio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lista_espera_movimientos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lista_espera_id')
                ->constrained('lista_espera')
                ->cascadeOnDelete();

            $table->integer('posicion_anterior');
            $table->integer('posicion_nueva');

            $table->foreignId('profesional_id')
                ->constrained('profesionales')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index('lista_espera_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lista_espera_movimientos');
    }
};
