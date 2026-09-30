<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos de slot que ofrece cada horario de centro.
 *
 * Desde junio de 2026 el tipo de slot es un catálogo global (migración
 * 2026_06_29_003), pero el cuadrante se sigue materializando con los tipos del
 * centro: cada horario elige los suyos del catálogo. Se crea vacío: cuando se
 * aplicó no había slots ni cuadrantes publicados, y cada centro los configura
 * en Filament.
 */
return new class extends Migration
{
    /**
     * Crea el pivote.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('horario_centro_tipo_slot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horario_centro_id')->constrained('horarios_centro')->cascadeOnDelete();
            $table->foreignId('tipo_slot_id')->constrained('tipos_slot')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['horario_centro_id', 'tipo_slot_id']);
        });
    }

    /**
     * Elimina el pivote.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('horario_centro_tipo_slot');
    }
};
