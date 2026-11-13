<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: bandeja de asignaciones del supervisor.
 *
 * Cada fila es algo que el sistema no ha podido decidir solo (RN-09, RN-10):
 * una persona sin centro, una historia sin referencia o una propuesta de
 * cambio de centro por cambio de domicilio. No se borran: se resuelven o se
 * descartan, con quién, cuándo y por qué.
 *
 * `centro_id` es la bandeja en la que aparece: el centro de quien actuaba al
 * detectarse. Si es nulo, la ven todos los supervisores de centros del tipo.
 * En las ambiguas, `centros_candidatos` la hace visible también a esos centros.
 *
 * @see docs/modulo-asignacion.md §7
 */
return new class extends Migration
{
    /**
     * Crea la tabla y el índice único parcial de entradas abiertas.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('asignaciones_pendientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciudadano_id')->constrained('ciudadanos')->restrictOnDelete();
            $table->foreignId('historia_id')->nullable()->constrained('historias_sociales')->restrictOnDelete();
            $table->string('tipo', 20)->comment('sin_centro | sin_referencia | cambio_domicilio');
            $table->string('tipo_centro', 100)->nullable();
            $table->string('motivo', 20)->nullable()->comment('sin_codigos | sin_cobertura | ambiguo | sin_elegibles');
            $table->foreignId('centro_id')->nullable()->constrained('centros')->restrictOnDelete()
                ->comment('Bandeja en la que aparece');
            $table->foreignId('centro_propuesto_id')->nullable()->constrained('centros')->restrictOnDelete()
                ->comment('Centro que corresponde por el nuevo domicilio (cambio_domicilio)');
            $table->jsonb('centros_candidatos')->nullable()->comment('Ids de centro en las ambiguas');
            $table->string('estado', 20)->default('pendiente')->comment('pendiente | resuelta | descartada');
            $table->foreignId('resuelta_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resuelta_en')->nullable();
            $table->text('motivo_resolucion')->nullable()->comment('Cifrado');
            $table->timestamps();

            $table->index(['estado', 'centro_id']);
            $table->index('ciudadano_id');
        });

        // Una sola entrada abierta por persona, tipo de pendiente y tipo de centro
        DB::statement(
            "CREATE UNIQUE INDEX asignaciones_pendientes_abierta ON asignaciones_pendientes (ciudadano_id, tipo, COALESCE(tipo_centro, '')) "
            ."WHERE estado = 'pendiente'"
        );
    }

    /**
     * Elimina la tabla.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('asignaciones_pendientes');
    }
};
