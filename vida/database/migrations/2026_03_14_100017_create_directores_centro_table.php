<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de responsables/directores de un centro.
 *
 * El director puede ser un Profesional del sistema (profesional_id relleno)
 * o una persona externa (campos nombre/telefono/email rellenos).
 * Ambas opciones son mutuamente excluyentes — se valida en el modelo.
 * El registro activo es el que tiene fecha_fin = null.
 *
 * @see Modules\Centro\Models\DirectorCentro
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directores_centro', function (Blueprint $table) {
            $table->id();

            $table->foreignId('centro_id')
                ->constrained('centros')
                ->cascadeOnDelete();

            // Director interno (profesional del sistema)
            $table->foreignId('profesional_id')
                ->nullable()
                ->constrained('profesionales')
                ->restrictOnDelete()
                ->comment('Director interno. Mutuamente excluyente con nombre/telefono/email');

            // Director externo
            $table->string('nombre', 200)->nullable()
                ->comment('Nombre del responsable externo');
            $table->string('telefono', 30)->nullable()
                ->comment('Teléfono del responsable externo');
            $table->string('email', 255)->nullable()
                ->comment('Email del responsable externo');

            // Vigencia
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable()
                ->comment('Null = director actual');

            $table->text('notas')->nullable();

            $table->timestamps();

            $table->index(['centro_id', 'fecha_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directores_centro');
    }
};
