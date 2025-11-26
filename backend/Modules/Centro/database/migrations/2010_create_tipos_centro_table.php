<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_centros', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique(); // Unique para evitar duplicados de tipos
            $table->text('descripcion')->nullable();
            // Gestión de plazas (mantengo tu lógica, pero con index para queries)
            $table->boolean('tiene_plazas')->default(false); // Renombrado para claridad
            $table->unsignedInteger('numero_plazas')->nullable();
            $table->text('criterio_asignacion_plazas')->nullable();
            // Flexibilidad: JSON para prestaciones default (array de IDs), público y schema dinámico
            $table->json('prestaciones_default')->nullable(); // e.g., [1, 5, 112] de la Guía 2024
            $table->json('publico_objetivo')->nullable(); // e.g., ["mayores", "familias", "mujeres_vg"]
            $table->json('schema_campos_dinamicos')->nullable(); // Para forms en frontend (e.g., campos específicos por tipo)
            $table->timestamps();
            // Índices para rendimiento (búsquedas por nombre, filtros comunes)
            $table->index('nombre');
            $table->index('tiene_plazas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_centros');
    }
};
