<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centro_profesional', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profesional_id')->constrained('profesionales')->onDelete('cascade');
            $table->foreignId('centro_id')->constrained('centros')->onDelete('cascade');
            $table->date('fecha_alta');
            $table->date('fecha_baja')->nullable(); // Permite asignaciones actuales (sin baja)
            $table->timestamps();
            $table->softDeletes();
            // Constraints e índices: unique por par profesional-centro para evitar duplicados concurrentes;
            // histórico se maneja con múltiples filas cerradas por fecha_baja
            $table->unique(['profesional_id', 'centro_id']); // Asume no re-asignaciones solapadas; ajusta si necesitas múltiples periodos
            $table->index(['fecha_alta', 'fecha_baja']); // Para queries históricas (e.g., profesionales activos en fecha X)
            $table->index(['centro_id', 'fecha_alta']); // Para "quién trabajaba en centro en fecha"
            $table->index(['profesional_id', 'fecha_alta']); // Para "historial de paso por centros del profesional"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centro_profesional');
    }
};
