<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de participantes en un hilo de mensajes.
 *
 * Un hilo tiene exactamente 2 participantes (mensajería 1 a 1).
 * Cada participante gestiona su estado de lectura y archivado
 * de forma independiente.
 *
 * @see docs/modulo-mensajes.md § 4.2
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajes_participantes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('hilo_id');
            $table->unsignedBigInteger('usuario_id');

            $table->enum('rol', ['remitente_inicial', 'participante'])
                ->comment('Rol del usuario en el hilo');

            $table->timestamp('fecha_ultima_lectura')->nullable()
                ->comment('Para calcular mensajes no leídos');

            $table->timestamp('archivado_en')->nullable()
                ->comment('El usuario puede archivar su vista del hilo');

            // Un usuario no puede ser participante dos veces en el mismo hilo
            $table->unique(['hilo_id', 'usuario_id']);

            $table->foreign('hilo_id')
                ->references('id')->on('mensajes_hilos')->cascadeOnDelete();
            $table->foreign('usuario_id')
                ->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes_participantes');
    }
};
