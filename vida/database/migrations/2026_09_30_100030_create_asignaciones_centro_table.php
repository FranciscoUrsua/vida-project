<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: centro asignado a cada persona, con historial.
 *
 * Una persona tiene como máximo una asignación vigente por tipo de centro
 * (RN-02), garantizado con un índice único parcial. El historial es aditivo:
 * una asignación nueva cierra la anterior con `fecha_fin` (RN-11); nunca se
 * cambia el centro de una fila existente.
 *
 * @see docs/modulo-asignacion.md §3
 */
return new class extends Migration
{
    /**
     * Crea la tabla y el índice único parcial.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('asignaciones_centro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciudadano_id')->constrained('ciudadanos')->restrictOnDelete();
            $table->string('tipo_centro', 100)->comment('Desnormalizado de centros.tipo_centro para la regla de uno vigente por tipo');
            $table->foreignId('centro_id')->constrained('centros')->restrictOnDelete();
            $table->string('modo', 20)->comment('geografico | eleccion | manual');
            $table->char('seccion_censal_codigo', 10)->nullable()->comment('Sección con la que se resolvió (modo geográfico)');
            $table->text('motivo')->nullable()->comment('Cifrado. Obligatorio en modo manual');
            $table->foreignId('asignado_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['centro_id', 'fecha_fin']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX asignaciones_centro_vigente_por_tipo ON asignaciones_centro (ciudadano_id, tipo_centro) '
            .'WHERE fecha_fin IS NULL AND deleted_at IS NULL'
        );
    }

    /**
     * Elimina la tabla.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('asignaciones_centro');
    }
};
