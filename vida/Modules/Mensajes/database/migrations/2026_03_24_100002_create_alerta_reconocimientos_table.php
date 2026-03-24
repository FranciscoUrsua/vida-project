<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de reconocimientos individuales de alertas.
 *
 * Registra cada acción de reconocimiento, descarte o herencia por
 * escalada, con trazabilidad de IP para auditoría GDPR.
 *
 * @see docs/modulo-mensajes.md § 4.1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerta_reconocimientos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('alerta_id');
            $table->unsignedBigInteger('usuario_id');

            $table->enum('tipo', ['reconocida', 'escalada', 'descartada'])
                ->comment('Naturaleza del reconocimiento');

            $table->timestamp('reconocida_en');

            $table->string('ip_address', 45)->nullable()
                ->comment('Dirección IP del request — auditoría');

            // Índice único: un usuario solo puede reconocer una alerta una vez
            $table->unique(['alerta_id', 'usuario_id']);

            $table->foreign('alerta_id')
                ->references('id')->on('alertas')->cascadeOnDelete();
            $table->foreign('usuario_id')
                ->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerta_reconocimientos');
    }
};
