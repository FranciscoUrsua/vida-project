<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_fichas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique(); // e.g., 'familia', 'economica'
            $table->text('descripcion')->nullable(); // Descripción breve (Guía págs. 2-3: áreas vitales)
            $table->json('schema')->nullable(); // JSON para campos dinámicos: {"ingresos": {"type": "number", "required": true}}
            $table->timestamps();

            $table->index('nombre'); // Para lookups rápidos en validaciones
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_fichas');
    }
};
