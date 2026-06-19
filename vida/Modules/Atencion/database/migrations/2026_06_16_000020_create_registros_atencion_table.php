<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla registros_atencion del módulo Atención.
 *
 * Almacena interacciones con ciudadanos que no implican
 * la apertura de Historia Social.
 */
return new class extends Migration
{
    /**
     * Crea la tabla registros_atencion con todas sus relaciones e índices.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('registros_atencion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciudadano_id')
                  ->constrained('ciudadanos')
                  ->cascadeOnDelete();
            $table->enum('tipo', ['informacion', 'actividad', 'contacto'])
                  ->default('informacion');
            $table->date('fecha');
            $table->foreignId('profesional_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->foreignId('prestacion_id')
                  ->nullable()
                  ->constrained('prestaciones')
                  ->nullOnDelete();
            $table->text('demanda')->nullable();
            $table->text('respuesta')->nullable();
            $table->enum('origen', ['manual', 'sistema'])->default('manual');
            $table->string('origen_tipo')->nullable();
            $table->unsignedBigInteger('origen_id')->nullable();
            $table->foreignId('cita_generada_id')
                  ->nullable()
                  ->constrained('citas')
                  ->nullOnDelete();
            $table->timestamps();

            $table->index('ciudadano_id');
            $table->index('profesional_id');
            $table->index(['origen_tipo', 'origen_id']);
            $table->index(['ciudadano_id', 'fecha']);
        });
    }

    /**
     * Elimina la tabla registros_atencion.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('registros_atencion');
    }
};
