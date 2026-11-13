<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: reparto de los casos de un profesional que deja el centro.
 *
 * El sistema propone un destino por caso; el supervisor revisa, cambia lo que
 * quiera y confirma. Hasta entonces ninguna asignación cambia (RN-08). Al
 * confirmar, las asignaciones nuevas apuntan al reparto (`reparto_id`).
 *
 * @see docs/modulo-asignacion.md §5
 */
return new class extends Migration
{
    /**
     * Crea las tablas y la referencia desde asignaciones_profesional.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('repartos_casos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centro_id')->constrained('centros')->restrictOnDelete();
            $table->foreignId('profesional_origen_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('iniciado_por_id')->constrained('users')->restrictOnDelete();
            $table->string('estado', 20)->default('propuesto')->comment('propuesto | confirmado | descartado');
            $table->text('motivo')->comment('Cifrado');
            $table->timestamp('confirmado_en')->nullable();
            $table->timestamps();

            $table->index(['centro_id', 'estado']);
        });

        Schema::create('repartos_casos_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reparto_id')->constrained('repartos_casos')->cascadeOnDelete();
            $table->foreignId('historia_id')->constrained('historias_sociales')->restrictOnDelete();
            $table->foreignId('unidad_convivencia_id')->nullable()->constrained('unidades_convivencia')->restrictOnDelete();
            $table->boolean('con_actividad');
            $table->foreignId('profesional_destino_id')->constrained('users')->restrictOnDelete();
            $table->boolean('modificada_por_supervisor')->default(false);
            $table->timestamps();

            $table->unique(['reparto_id', 'historia_id']);
        });

        Schema::table('asignaciones_profesional', function (Blueprint $table) {
            $table->foreignId('reparto_id')->nullable()->constrained('repartos_casos')->restrictOnDelete();
        });
    }

    /**
     * Elimina las tablas y la referencia.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('asignaciones_profesional', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reparto_id');
        });
        Schema::dropIfExists('repartos_casos_lineas');
        Schema::dropIfExists('repartos_casos');
    }
};
