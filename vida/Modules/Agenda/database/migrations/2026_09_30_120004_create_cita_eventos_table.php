<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historial inmutable de solicitudes y citas (docs/modulo-citas.md §2.4).
 *
 * Solo inserción: un trigger rechaza UPDATE y DELETE también fuera de Eloquent.
 * Forma parte del expediente de atención y no entra en la purga de `audits`.
 */
return new class extends Migration
{
    /**
     * Crea la tabla, el check y el trigger.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('cita_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_cita_id')->nullable()->constrained('solicitudes_cita')->restrictOnDelete();
            $table->foreignId('cita_id')->nullable()->constrained('citas')->restrictOnDelete();
            $table->string('accion', 40)->comment('AccionCitaEvento');
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_tipo', 20)->comment('ActorTipoCitaEvento');
            $table->string('canal', 20)->nullable()->comment('CanalCitaEvento');
            $table->string('pedido_por', 20)->nullable()->comment('PedidoPor');
            $table->string('estado_antes', 30)->nullable();
            $table->string('estado_despues', 30)->nullable();
            $table->text('motivo')->nullable()->comment('Cifrado');
            $table->jsonb('datos')->nullable()->comment('Slots, profesionales, fechas. Sin datos personales en claro');
            $table->timestampTz('created_at')->useCurrent();

            $table->index('cita_id');
            $table->index('solicitud_cita_id');
            $table->index('accion');
        });

        DB::statement('ALTER TABLE cita_eventos ADD CONSTRAINT cita_eventos_sujeto_check CHECK (solicitud_cita_id IS NOT NULL OR cita_id IS NOT NULL)');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION cita_eventos_solo_insercion() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'cita_eventos es de solo inserción: el historial de la cita no se modifica ni se borra';
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement('CREATE TRIGGER cita_eventos_inmutable BEFORE UPDATE OR DELETE ON cita_eventos FOR EACH ROW EXECUTE FUNCTION cita_eventos_solo_insercion()');
    }

    /**
     * Elimina el trigger, la función y la tabla.
     *
     * @return void
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS cita_eventos_inmutable ON cita_eventos');
        DB::statement('DROP FUNCTION IF EXISTS cita_eventos_solo_insercion()');
        Schema::dropIfExists('cita_eventos');
    }
};
