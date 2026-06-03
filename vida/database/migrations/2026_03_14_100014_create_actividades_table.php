<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Centro\Models\Actividad;

/**
 * Actividades programadas en un centro.
 *
 * Una actividad es un programa (taller, grupo, charla, seminario...)
 * que se materializa en sesiones convocadas explícitamente.
 * VIDA 360 gestiona inscripciones y aforo; la operativa interna
 * corresponde a las herramientas propias del centro.
 *
 * modo_acceso valores: libre | prescripcion | mixta
 *   mixta = hay cupo reservado para prescripciones y cupo libre simultáneamente.
 *
 * @see Actividad
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividades', function (Blueprint $table) {
            $table->id();

            $table->foreignId('centro_id')
                ->constrained('centros')
                ->cascadeOnDelete();

            $table->foreignId('tipo_actividad_id')
                ->constrained('tipos_actividad')
                ->restrictOnDelete();

            $table->string('nombre', 200);
            $table->text('descripcion')->nullable();
            $table->string('modo_acceso', 20)->default('libre')
                ->comment('libre | prescripcion | mixta');
            $table->unsignedInteger('aforo_total')->nullable()
                ->comment('Aforo máximo total. Null = sin límite');
            $table->unsignedInteger('aforo_prescripcion')->nullable()
                ->comment('Cupo reservado para prescripciones. Solo relevante si modo_acceso = mixta');
            $table->boolean('requiere_inscripcion_centro')->default(false)
                ->comment('Si true, el ciudadano debe tener InscripcionCentro activa');
            $table->boolean('activa')->default(true);
            $table->date('fecha_alta');
            $table->date('fecha_baja')->nullable();
            $table->text('notas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['centro_id', 'activa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividades');
    }
};
