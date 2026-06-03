<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Centro\Models\ListaEspera;

/**
 * Lista de espera vinculada a una prescripción.
 *
 * Cuando una prescripción no puede satisfacerse de inmediato, se crea
 * un registro en lista de espera. El ámbito puede ser local (colección
 * de plazas de un centro) o de red (pool de plazas de toda la red).
 * Ambos campos son mutuamente excluyentes — se valida en el modelo.
 *
 * La asignación nunca es automática: cuando se libera una plaza el sistema
 * alerta al TSR activo del ciudadano (profesional_alerta_id), que puede no
 * coincidir con el profesional que realizó la prescripción original.
 *
 * estado valores: activa | asignada | cancelada
 *
 * @see ListaEspera
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lista_espera', function (Blueprint $table) {
            $table->id();

            $table->foreignId('prescripcion_id')
                ->unique()
                ->constrained('prescripciones')
                ->cascadeOnDelete();

            // Ámbito de la lista de espera (mutuamente excluyentes)
            $table->foreignId('coleccion_plazas_id')
                ->nullable()
                ->constrained('colecciones_plazas')
                ->nullOnDelete()
                ->comment('Lista de espera local de una colección. Mutuamente excluyente con red_id');
            $table->foreignId('red_id')
                ->nullable()
                ->constrained('redes')
                ->nullOnDelete()
                ->comment('Lista de espera de red. Mutuamente excluyente con coleccion_plazas_id');

            $table->foreignId('profesional_alerta_id')
                ->constrained('profesionales', 'id')
                ->restrictOnDelete()
                ->comment('TSR activo en el momento de generar la alerta');

            $table->unsignedInteger('posicion')
                ->comment('Posición en la lista. Se recalcula cuando hay movimientos');
            $table->dateTime('fecha_entrada');
            $table->dateTime('fecha_alerta')->nullable()
                ->comment('Última vez que se notificó al profesional de referencia');
            $table->string('estado', 20)->default('activa')
                ->comment('activa | asignada | cancelada');

            $table->timestamps();

            $table->index(['coleccion_plazas_id', 'estado']);
            $table->index(['red_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lista_espera');
    }
};
