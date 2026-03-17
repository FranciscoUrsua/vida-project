<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plazas individuales dentro de un espacio.
 *
 * Una plaza es la unidad mínima asignable a una persona.
 * El estado se mantiene desnormalizado para consultas rápidas de disponibilidad.
 * La ocupación efectiva se rastrea a través de la Prescripcion activa que la apunta.
 *
 * estado valores: libre | ocupada | reservada | mantenimiento
 *
 * @see Modules\Centro\Models\Plaza
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plazas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('espacio_id')
                ->constrained('espacios')
                ->cascadeOnDelete();

            $table->string('nombre', 50)->comment('Ej: "Cama 1", "Cama 2"');
            $table->string('estado', 20)->default('libre')
                ->comment('libre | ocupada | reservada | mantenimiento');
            $table->boolean('activa')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['espacio_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plazas');
    }
};
