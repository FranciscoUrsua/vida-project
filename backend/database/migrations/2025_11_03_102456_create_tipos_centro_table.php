<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_centro', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100); // e.g., 'Centro de Atención Primaria'
            $table->text('descripcion')->nullable(); // Descripción detallada
            $table->boolean('plazas'); // Indica si tiene plazas limitadas
            $table->integer('numero_plazas')->nullable(); // Número de plazas si plazas=true
            $table->text('criterio_asignacion_plazas')->nullable(); // Criterio para asignar plazas
            $table->text('publico_objetivo'); // Público objetivo (e.g., 'Familias vulnerables')
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_centro');
    }
};
