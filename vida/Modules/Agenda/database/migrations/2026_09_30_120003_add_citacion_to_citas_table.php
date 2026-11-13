<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Amplía `citas` para la citación (docs/instrucciones-cli/2026-09-citas-implementacion.md, paso 2).
 *
 * Las citas existentes toman el tipo genérico «cita» y el modo
 * `profesional_concreto`. `ciudadano_id` pasa a admitir nulos solo para citas
 * externas pendientes de identificar (lo valida el modelo).
 */
return new class extends Migration
{
    /**
     * Añade columnas, rellena las existentes y crea el índice de idempotencia externa.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->foreignId('solicitud_cita_id')->nullable()->after('slot_id')->constrained('solicitudes_cita')->restrictOnDelete();
            $table->foreignId('tipo_cita_id')->nullable()->after('tipo_slot_id')->constrained('tipos_cita')->restrictOnDelete();
            $table->string('modalidad', 20)->default('presencial');
            $table->string('modo_asignacion', 30)->default('profesional_concreto');
            $table->foreignId('cita_anterior_id')->nullable()->unique()->constrained('citas')->restrictOnDelete()
                ->comment('Una cita solo se reprograma una vez');
            $table->text('datos_identificacion_externos')->nullable()->comment('Cifrado (encrypted:array)');
            $table->boolean('pendiente_cierre')->default(false);
            $table->timestamp('pendiente_cierre_desde')->nullable();
            $table->timestamp('aviso_cierre_supervisor_en')->nullable()->comment('Aviso al supervisor enviado (una vez)');
            $table->string('pedido_por_cancelacion', 20)->nullable()->comment('PedidoPor');
            $table->index(['profesional_id', 'pendiente_cierre']);
        });

        $generico = DB::table('tipos_cita')->where('codigo', 'cita')->value('id');
        DB::table('citas')->whereNull('tipo_cita_id')->update(['tipo_cita_id' => $generico]);

        DB::statement('ALTER TABLE citas ALTER COLUMN tipo_cita_id SET NOT NULL');
        DB::statement('ALTER TABLE citas ALTER COLUMN ciudadano_id DROP NOT NULL');
        DB::statement('CREATE UNIQUE INDEX citas_origen_referencia_externa_unique ON citas (origen, referencia_externa) WHERE referencia_externa IS NOT NULL');
    }

    /**
     * Revierte la ampliación.
     *
     * @return void
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS citas_origen_referencia_externa_unique');

        Schema::table('citas', function (Blueprint $table) {
            $table->dropIndex(['profesional_id', 'pendiente_cierre']);
            $table->dropConstrainedForeignId('solicitud_cita_id');
            $table->dropConstrainedForeignId('tipo_cita_id');
            $table->dropConstrainedForeignId('cita_anterior_id');
            $table->dropColumn(['modalidad', 'modo_asignacion', 'datos_identificacion_externos', 'pendiente_cierre', 'pendiente_cierre_desde', 'aviso_cierre_supervisor_en', 'pedido_por_cancelacion']);
        });
    }
};
