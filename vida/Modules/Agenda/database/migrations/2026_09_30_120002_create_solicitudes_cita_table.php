<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitudes de cita (docs/modulo-citas.md §2.2): la demanda, separada de la
 * cita. Toda cita del canal interno nace de una. Solo la escriben los servicios
 * de Agenda.
 */
return new class extends Migration
{
    /**
     * Crea la tabla.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('solicitudes_cita', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciudadano_id')->constrained('ciudadanos')->restrictOnDelete();
            $table->foreignId('centro_id')->constrained('centros')->restrictOnDelete();
            $table->foreignId('solicitante_id')->constrained('users')->restrictOnDelete();
            $table->string('canal', 20)->comment('CanalSolicitudCita');
            $table->foreignId('tipo_cita_id')->constrained('tipos_cita')->restrictOnDelete();
            $table->string('urgencia', 20)->comment('UrgenciaCita');
            $table->string('destino', 30)->comment('DestinoCita');
            $table->foreignId('profesional_destino_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('servicio_destino', 100)->nullable()->comment('cargos.slug');
            $table->date('no_antes_de')->nullable();
            $table->date('no_despues_de');
            $table->text('motivo')->nullable()->comment('Cifrado. Solo roles con acceso a la Historia Social');
            $table->text('observaciones_citacion')->nullable()->comment('Cifrado');
            $table->nullableMorphs('contexto');
            $table->foreignId('solicitud_anterior_id')->nullable()->constrained('solicitudes_cita')->restrictOnDelete();
            $table->string('estado', 20)->default('pendiente')->comment('EstadoSolicitudCita');
            $table->foreignId('gestionada_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resuelta_en')->nullable();
            $table->text('motivo_cierre')->nullable()->comment('Cifrado. Obligatorio en desistida y anulada');
            $table->timestamp('aviso_fuera_plazo_en')->nullable()->comment('Aviso al supervisor por fecha límite vencida (una vez)');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['centro_id', 'estado', 'urgencia', 'no_despues_de']);
            $table->index('ciudadano_id');
        });
    }

    /**
     * Elimina la tabla.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_cita');
    }
};
