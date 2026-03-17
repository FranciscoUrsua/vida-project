<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sesiones concretas de una actividad.
 *
 * Una sesión es la materialización de una actividad en una fecha y hora.
 * Los aforos de sesión sobreescriben los de la actividad cuando se especifican.
 *
 * estado valores: programada | celebrada | cancelada
 *
 * @see Modules\Centro\Models\SesionActividad
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesiones_actividad', function (Blueprint $table) {
            $table->id();

            $table->foreignId('actividad_id')
                ->constrained('actividades')
                ->cascadeOnDelete();

            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin')->nullable();

            $table->unsignedInteger('aforo_total')->nullable()
                ->comment('Sobreescribe el de la actividad si se especifica');
            $table->unsignedInteger('aforo_prescripcion')->nullable()
                ->comment('Sobreescribe el de la actividad si se especifica. Solo relevante en modo mixta');

            $table->string('estado', 20)->default('programada')
                ->comment('programada | celebrada | cancelada');
            $table->text('notas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['actividad_id', 'fecha']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesiones_actividad');
    }
};
