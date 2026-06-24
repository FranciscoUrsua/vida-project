<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de asignaciones de profesional de referencia a Historia Social.
 *
 * Un profesional puede ser responsable de una historia durante un período
 * acotado. El registro vigente tiene fecha_fin null. Los registros cerrados
 * mantienen el historial completo de cambios de responsable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignaciones_profesional', function (Blueprint $table) {
            $table->id();

            $table->foreignId('historia_id')
                ->constrained('historias_sociales')
                ->onDelete('restrict');

            $table->foreignId('profesional_id')
                ->constrained('users')
                ->onDelete('restrict');

            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('historia_id');
            $table->index('profesional_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones_profesional');
    }
};
