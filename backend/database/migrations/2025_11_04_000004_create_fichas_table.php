<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fichas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('valoracion_id')->constrained('valoraciones')->onDelete('cascade');
            $table->foreignId('tipo_ficha_id')->constrained('tipos_fichas')->onDelete('restrict');
            $table->json('datos'); // Dinámico: e.g., {"ingresos": 800, "hijos": 2}
            $table->text('notas')->nullable(); // Texto libre
            $table->timestamps();
            $table->softDeletes(); // Para revisiones históricas (Arquitectura.md)

            $table->index(['valoracion_id']);
            $table->index(['tipo_ficha_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fichas');
    }
};
