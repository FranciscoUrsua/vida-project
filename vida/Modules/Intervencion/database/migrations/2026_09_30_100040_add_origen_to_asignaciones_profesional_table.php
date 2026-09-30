<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Amplía las asignaciones de profesional de referencia con su origen y los
 * datos del reparto (docs/modulo-asignacion.md §4).
 *
 * Las asignaciones existentes quedan como `quien_abre`, sin centro y sin contar
 * en el reparto: se conservan tal cual (RN-12). `sorteo` guarda, en cada
 * asignación que cuenta, los profesionales del reparto de ese día con su peso,
 * lo esperado y lo recibido, y el elegido: el sorteo es auditable y el cálculo
 * de lo esperado usa el reparto de la fecha de cada entrada.
 */
return new class extends Migration
{
    /**
     * Añade las columnas.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('asignaciones_profesional', function (Blueprint $table) {
            $table->foreignId('centro_id')->nullable()->after('profesional_id')->constrained('centros')->restrictOnDelete();
            $table->string('origen', 20)->default('quien_abre')
                ->comment('sorteo | eleccion | unidad_convivencia | reparto | manual | quien_abre');
            $table->boolean('cuenta_en_reparto')->default(false);
            $table->jsonb('sorteo')->nullable();
            $table->text('motivo')->nullable()->comment('Cifrado. Obligatorio en manual');
            $table->foreignId('asignado_por_id')->nullable()->constrained('users')->restrictOnDelete();

            $table->index(['centro_id', 'cuenta_en_reparto', 'fecha_inicio']);
        });
    }

    /**
     * Elimina las columnas.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('asignaciones_profesional', function (Blueprint $table) {
            $table->dropIndex(['centro_id', 'cuenta_en_reparto', 'fecha_inicio']);
            $table->dropConstrainedForeignId('centro_id');
            $table->dropConstrainedForeignId('asignado_por_id');
            $table->dropColumn(['origen', 'cuenta_en_reparto', 'sorteo', 'motivo']);
        });
    }
};
