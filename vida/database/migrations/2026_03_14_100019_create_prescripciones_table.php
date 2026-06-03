<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Centro\Models\Prescripcion;

/**
 * Prescripciones de plazas o sesiones de actividad.
 *
 * Una prescripción es el acto por el que un profesional asigna o
 * reserva un recurso (plaza en una colección o puesto en una sesión)
 * para un ciudadano concreto, en el contexto de un plan de intervención.
 *
 * tipo_destino + destino_id implementan una relación polimórfica manual:
 *   - tipo_destino = 'coleccion_plazas' → destino_id apunta a colecciones_plazas.id
 *   - tipo_destino = 'sesion_actividad'  → destino_id apunta a sesiones_actividad.id
 *
 * estado valores: pendiente | en_lista_espera | asignada | activa | finalizada | cancelada
 *
 * plan_intervencion_id: FK sin constraint declarada — la tabla planes_intervencion
 * se creará con el módulo Intervención. Añadir constraint entonces.
 *
 * @see Prescripcion
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescripciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('profesional_id')
                ->constrained('profesionales')
                ->restrictOnDelete();

            $table->foreignId('ciudadano_id')
                ->constrained('ciudadanos')
                ->restrictOnDelete();

            // FK sin constraint declarada — tabla aún no implementada
            $table->unsignedBigInteger('plan_intervencion_id')->nullable()
                ->comment('FK a planes_intervencion.id (sin constraint hasta implementar módulo Intervención)');

            // Destino polimórfico manual
            $table->string('tipo_destino', 30)
                ->comment('coleccion_plazas | sesion_actividad');
            $table->unsignedBigInteger('destino_id')
                ->comment('PK de la entidad destino según tipo_destino');

            // Plaza concreta asignada (solo cuando tipo_destino = coleccion_plazas)
            $table->foreignId('plaza_id')
                ->nullable()
                ->constrained('plazas')
                ->nullOnDelete();

            $table->string('estado', 30)->default('pendiente')
                ->comment('pendiente | en_lista_espera | asignada | activa | finalizada | cancelada');

            $table->date('fecha_prescripcion');
            $table->date('fecha_asignacion')->nullable()
                ->comment('Fecha en que se asignó plaza o sesión concreta');
            $table->date('fecha_inicio')->nullable()
                ->comment('Fecha de inicio efectivo del servicio');
            $table->date('fecha_fin')->nullable()
                ->comment('Fecha de fin efectivo o previsto');
            $table->text('motivo_cancelacion')->nullable();
            $table->text('notas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['ciudadano_id', 'estado']);
            $table->index(['tipo_destino', 'destino_id']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescripciones');
    }
};
