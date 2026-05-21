<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profesionales asignados a un servicio.
 *
 * Un profesional puede estar asignado a varios servicios simultáneamente.
 * Sin gestión de agenda ni control de carga de trabajo en VIDA 360.
 * La baja se registra con fecha_baja (nunca se eliminan registros históricos).
 *
 * @see Modules\Centro\Models\Servicio
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profesional_servicio', function (Blueprint $table) {
            $table->id();

            $table->foreignId('servicio_id')
                ->constrained('servicios')
                ->cascadeOnDelete();

            $table->foreignId('profesional_id')
                ->constrained('profesionales')
                ->restrictOnDelete();

            $table->date('fecha_alta');
            $table->date('fecha_baja')->nullable();

            $table->index(['servicio_id', 'fecha_baja']);
            $table->index('profesional_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profesional_servicio');
    }
};
