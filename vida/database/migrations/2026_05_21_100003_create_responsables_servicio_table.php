<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Centro\Models\ResponsableServicio;

/**
 * Historial de responsables de un servicio.
 *
 * El cargo (nombre) es un atributo del servicio, no del profesional. El
 * responsable asume el cargo al ser nombrado y lo deja al cesar. A diferencia
 * de DirectorCentro, el responsable de un servicio es siempre un profesional
 * con cuenta en VIDA 360 — no existe la figura de responsable externo.
 *
 * El registro activo es el que tiene fecha_fin = null. Solo puede haber un
 * responsable activo por servicio.
 *
 * @see ResponsableServicio
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('responsables_servicio', function (Blueprint $table) {
            $table->id();

            $table->foreignId('servicio_id')
                ->constrained('servicios')
                ->cascadeOnDelete();

            $table->foreignId('profesional_id')
                ->constrained('profesionales')
                ->restrictOnDelete()
                ->comment('Siempre un profesional de VIDA 360. No existe responsable externo');

            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable()
                ->comment('Null = responsable actual');

            $table->text('notas')->nullable();

            $table->timestamps();

            $table->index(['servicio_id', 'fecha_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responsables_servicio');
    }
};
