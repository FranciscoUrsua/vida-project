<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('valoraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historia_id')->constrained('historias')->onDelete('cascade');
            $table->foreignId('profesional_id')->constrained('profesionales')->onDelete('restrict'); // Quién realiza (mismo que abre Historia)
            $table->enum('tipo', ['inicial', 'sucesiva'])->default('inicial');
            $table->date('fecha_realizacion');
            $table->text('resumen')->nullable(); // Resumen manual
            $table->json('resumen_ia')->nullable(); // Futuro: extracciones IA de texto libre
            $table->timestamps();

            // Índices para queries comunes (por historia/tipo/fecha)
            $table->index(['historia_id']);
            $table->index(['profesional_id']);
            $table->index(['tipo']);
            $table->index(['fecha_realizacion']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('valoraciones');
    }
};
