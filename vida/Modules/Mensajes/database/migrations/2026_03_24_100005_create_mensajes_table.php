<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de mensajes individuales dentro de un hilo.
 *
 * Los adjuntos se gestionan a través de spatie/laravel-medialibrary
 * en la colección 'adjuntos_mensaje'.
 *
 * @see docs/modulo-mensajes.md § 4.2
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('hilo_id');
            $table->unsignedBigInteger('remitente_id');
            $table->text('cuerpo');
            $table->timestamps();

            // Índice para ordenación eficiente dentro de un hilo
            $table->index(['hilo_id', 'created_at']);

            $table->foreign('hilo_id')
                ->references('id')->on('mensajes_hilos')->cascadeOnDelete();
            $table->foreign('remitente_id')
                ->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes');
    }
};
