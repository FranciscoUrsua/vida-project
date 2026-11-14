<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de valoraciones de los indicadores de los objetivos (fase 3, F3-7 y bloque C).
 *
 * Cada valoración (en un seguimiento o al editar el plan) es una fila nueva; corregir
 * es valorar otra vez (principio 4.2). `plan_objetivo_indicadores.valoracion_actual`
 * queda como la última. Solo inserción: un trigger rechaza UPDATE y DELETE.
 */
return new class extends Migration
{
    /**
     * Crea la tabla y el trigger.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('plan_indicador_valoraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_objetivo_indicador_id')->constrained('plan_objetivo_indicadores');
            $table->foreignId('seguimiento_id')->nullable()->constrained('seguimientos_plan');
            $table->string('valoracion', 30);
            $table->text('comentario')->nullable();
            $table->foreignId('profesional_id')->nullable()->constrained('users');
            $table->date('fecha');
            $table->timestamps();

            $table->index('plan_objetivo_indicador_id');
        });

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION plan_indicador_valoraciones_solo_insercion() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'plan_indicador_valoraciones es de solo inserción: para corregir, valora de nuevo';
            END;
            $$ LANGUAGE plpgsql
            SQL);

        DB::statement('CREATE TRIGGER plan_indicador_valoraciones_inmutable BEFORE UPDATE OR DELETE ON plan_indicador_valoraciones FOR EACH ROW EXECUTE FUNCTION plan_indicador_valoraciones_solo_insercion()');
    }

    /**
     * Elimina el trigger y la tabla.
     *
     * @return void
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS plan_indicador_valoraciones_inmutable ON plan_indicador_valoraciones');
        DB::statement('DROP FUNCTION IF EXISTS plan_indicador_valoraciones_solo_insercion()');
        Schema::dropIfExists('plan_indicador_valoraciones');
    }
};
