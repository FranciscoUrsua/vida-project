<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea `alerta_destinatarios`: una fila por cada persona que debe reconocer
 * o cerrar una alerta o aviso, con su propio estado.
 *
 * Los destinatarios de una alerta dirigida a un colectivo (rol en una UO) se
 * fijan al crearla: cada uno debe reconocerla por su cuenta y la escalada es
 * por destinatario (decisión del desarrollador de 2026-09-26).
 */
return new class extends Migration
{
    /**
     * Crea la tabla.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('alerta_destinatarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alerta_id')->constrained('alertas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users');
            $table->enum('estado', ['pendiente', 'reconocida', 'escalada', 'vencida'])->default('pendiente');
            $table->timestamp('atendida_en')->nullable()
                ->comment('Momento en que el destinatario la reconoció o descartó');
            $table->timestamp('escalada_en')->nullable();
            $table->foreignId('escalada_a_usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['alerta_id', 'usuario_id']);
            $table->index(['usuario_id', 'estado']);
            $table->index(['escalada_a_usuario_id', 'estado']);
        });
    }

    /**
     * Elimina la tabla.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('alerta_destinatarios');
    }
};
