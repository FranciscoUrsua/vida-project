<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de hilos de mensajería interna.
 *
 * Un hilo agrupa el intercambio de mensajes entre dos profesionales
 * bajo un asunto común. La mensajería es estrictamente 1 a 1.
 *
 * @see docs/modulo-mensajes.md § 4.2
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajes_hilos', function (Blueprint $table) {
            $table->id();
            $table->string('asunto');
            $table->unsignedBigInteger('creado_por_id');
            $table->timestamps();

            $table->foreign('creado_por_id')
                ->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes_hilos');
    }
};
