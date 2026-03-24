<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de referencias informativas entre mensajes y ciudadanos.
 *
 * Permite navegar desde un mensaje al expediente del ciudadano.
 * No implica registro en la Historia Social; esa acción es explícita
 * y se materializa en mensajes_registro_historia.
 *
 * @see docs/modulo-mensajes.md § 4.2
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajes_referencias_ciudadano', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('mensaje_id');
            $table->unsignedBigInteger('ciudadano_id');

            $table->timestamp('created_at')->useCurrent();

            // Índice para consultas desde el expediente del ciudadano
            $table->index('ciudadano_id');

            $table->foreign('mensaje_id')
                ->references('id')->on('mensajes')->cascadeOnDelete();
            $table->foreign('ciudadano_id')
                ->references('id')->on('ciudadanos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes_referencias_ciudadano');
    }
};
