<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('valoraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historia_id')
                ->constrained('historias_sociales')
                ->onDelete('restrict');
            $table->foreignId('entrevista_id')
                ->nullable()
                ->constrained('entrevistas')
                ->onDelete('set null');
            $table->foreignId('profesional_id')
                ->constrained('users')
                ->onDelete('restrict');
            $table->foreignId('tipo_valoracion_id')
                ->constrained('tipo_valoraciones')
                ->onDelete('restrict');
            $table->date('fecha');
            $table->string('estado', 30)->default('borrador')->comment('borrador, completada, revisada');
            $table->text('resumen')->nullable();
            $table->timestamps();

            $table->index('historia_id');
            $table->index('entrevista_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valoraciones');
    }
};
