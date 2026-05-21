<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitudes de tramitación de una prestación a un servicio.
 *
 * Se generan cuando un TSR prescribe desde un plan de intervención una
 * prestación asociada a un servicio. El responsable del servicio ve la
 * solicitud en su bandeja; la tramitación posterior puede ocurrir dentro
 * de VIDA o fuera (derivación a otra administración).
 *
 * estado: pendiente | en_tramite | resuelta | denegada | derivada_externa
 *
 * Las anotaciones de seguimiento (actualizaciones, notas de tramitación)
 * pertenecen al módulo de Intervención como hechos de la historia social.
 *
 * plan_intervencion_id: FK con constraint declarada. La tabla existe desde
 * el módulo Intervención (planes_intervencion). Cancelar un plan no elimina
 * solicitudes vinculadas (restrictOnDelete).
 *
 * @see Modules\Centro\Models\SolicitudServicio
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_servicio', function (Blueprint $table) {
            $table->id();

            $table->foreignId('servicio_id')
                ->constrained('servicios')
                ->restrictOnDelete();

            $table->foreignId('ciudadano_id')
                ->constrained('ciudadanos')
                ->restrictOnDelete();

            $table->foreignId('profesional_id')
                ->constrained('profesionales')
                ->restrictOnDelete()
                ->comment('TSR que genera la solicitud');

            $table->foreignId('plan_intervencion_id')
                ->nullable()
                ->constrained('planes_intervencion')
                ->nullOnDelete()
                ->comment('Nullable: solicitudes pueden existir sin plan asociado');

            $table->foreignId('prestacion_id')
                ->constrained('prestaciones')
                ->restrictOnDelete();

            $table->string('estado', 30)->default('pendiente')
                ->comment('pendiente | en_tramite | resuelta | denegada | derivada_externa');

            $table->date('fecha_solicitud');
            $table->date('fecha_resolucion')->nullable()
                ->comment('Se rellena automáticamente al pasar a estado resuelta');

            $table->text('notas')->nullable()
                ->comment('Nota inicial del TSR');

            $table->timestamps();

            $table->index(['servicio_id', 'estado']);
            $table->index(['ciudadano_id', 'estado']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_servicio');
    }
};
