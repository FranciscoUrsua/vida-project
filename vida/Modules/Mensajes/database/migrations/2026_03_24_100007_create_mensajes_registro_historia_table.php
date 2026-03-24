<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de registros de mensajes en la Historia Social.
 *
 * Materializa la decisión explícita del TSR de incorporar un mensaje
 * (o una versión editada del mismo) al expediente de un ciudadano.
 * Cada registro representa una entrada de tipo 'comunicacion_interna'
 * en la Historia Social.
 *
 * Solo el TSR responsable del expediente puede crear estos registros.
 *
 * @see docs/modulo-mensajes.md § 3.2
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajes_registro_historia', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('mensaje_id')
                ->comment('Mensaje original del que proviene el registro');
            $table->unsignedBigInteger('ciudadano_id');
            $table->unsignedBigInteger('registrado_por_id')
                ->comment('Debe ser el TSR responsable del expediente');

            $table->text('cuerpo_registrado')
                ->comment('Copia editada del mensaje; puede diferir del original');

            $table->enum('visibilidad', ['privada', 'profesionales'])
                ->default('profesionales')
                ->comment('Visibilidad en la Historia Social');

            $table->timestamp('registrado_en')->useCurrent();
            $table->timestamps();

            // Índice para consultas cronológicas desde la Historia Social
            $table->index(['ciudadano_id', 'registrado_en']);

            $table->foreign('mensaje_id')
                ->references('id')->on('mensajes')->restrictOnDelete();
            $table->foreign('ciudadano_id')
                ->references('id')->on('ciudadanos')->restrictOnDelete();
            $table->foreign('registrado_por_id')
                ->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes_registro_historia');
    }
};
