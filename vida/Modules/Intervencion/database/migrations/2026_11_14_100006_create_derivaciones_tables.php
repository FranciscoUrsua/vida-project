<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Derivación a atención especializada (fase 3, F3-5, F3-6 y B2).
 *
 * El TSR deriva desde el plan principal (y, si quiere, desde un objetivo) eligiendo una
 * prestación de derivación y un centro de destino que ofrezca su tipo de plan. La
 * dirección o la supervisión del destino la acepta asignando responsable (se abre el
 * plan especializado en borrador) o la rechaza con motivo; el TSR puede anularla mientras
 * está pendiente.
 *
 * Los motivos son datos de la persona: van cifrados (`encrypted` en los modelos), por
 * eso son `text`. `derivacion_eventos` es el historial: solo inserción, con un trigger
 * que rechaza UPDATE y DELETE (como `cita_eventos`, principio 4.2).
 */
return new class extends Migration
{
    /**
     * Crea las tablas y el trigger del historial.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('derivaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historia_id')->constrained('historias_sociales');
            $table->foreignId('plan_origen_id')->constrained('planes_intervencion');
            $table->foreignId('plan_objetivo_id')->nullable()->constrained('plan_objetivos');
            $table->foreignId('prestacion_id')->constrained('prestaciones');
            $table->foreignId('tipo_plan_id')->constrained('tipos_plan');
            $table->foreignId('centro_destino_id')->constrained('centros');
            $table->text('motivo');
            $table->string('estado', 20)->default('pendiente');
            $table->foreignId('derivada_por_id')->constrained('users');
            $table->foreignId('resuelta_por_id')->nullable()->constrained('users');
            $table->foreignId('profesional_responsable_id')->nullable()->constrained('users');
            $table->foreignId('plan_especializado_id')->nullable()->constrained('planes_intervencion');
            $table->text('motivo_rechazo')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->timestamp('resuelta_en')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['centro_destino_id', 'estado']);
            $table->index('historia_id');
        });

        DB::statement("ALTER TABLE derivaciones ADD CONSTRAINT derivaciones_estado_check CHECK (estado IN ('pendiente', 'aceptada', 'rechazada', 'anulada'))");

        Schema::create('derivacion_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('derivacion_id')->constrained('derivaciones');
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('estado_antes', 20)->nullable();
            $table->string('estado_despues', 20);
            $table->text('motivo')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('derivacion_id');
        });

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION derivacion_eventos_solo_insercion() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'derivacion_eventos es de solo inserción: el historial de la derivación no se modifica ni se borra';
            END;
            $$ LANGUAGE plpgsql
            SQL);

        DB::statement('CREATE TRIGGER derivacion_eventos_inmutable BEFORE UPDATE OR DELETE ON derivacion_eventos FOR EACH ROW EXECUTE FUNCTION derivacion_eventos_solo_insercion()');
    }

    /**
     * Elimina el trigger y las tablas.
     *
     * @return void
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS derivacion_eventos_inmutable ON derivacion_eventos');
        DB::statement('DROP FUNCTION IF EXISTS derivacion_eventos_solo_insercion()');
        Schema::dropIfExists('derivacion_eventos');
        Schema::dropIfExists('derivaciones');
    }
};
