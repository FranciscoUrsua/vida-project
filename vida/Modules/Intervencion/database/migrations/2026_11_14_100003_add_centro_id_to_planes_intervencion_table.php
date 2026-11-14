<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Centro del plan (fase 3, F3-2 y A2) y un plan vigente por tipo (F3-1, A1.3).
 *
 * - `centro_id`: el centro donde se abre el plan. Nullable mientras queden planes
 *   antiguos sin centro deducible; los nuevos lo exigen en el modelo.
 * - Relleno: el centro de la UO de la historia (centro y UO 1:1, addenda D1).
 * - Índice único parcial: una historia no tiene dos planes vigentes (borrador o activo)
 *   del mismo tipo de plan. `en_revision` es la versión sustituida de un plan
 *   (`crearNuevaVersion()`) y `cerrado` ya no cuenta.
 */
return new class extends Migration
{
    /**
     * Añade la columna, la rellena y crea el índice.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('planes_intervencion', function (Blueprint $table) {
            $table->foreignId('centro_id')->nullable()->after('tipo_plan_id')->constrained('centros');
            $table->index('centro_id');
        });

        DB::statement('
            UPDATE planes_intervencion p
               SET centro_id = c.id
              FROM historias_sociales h
              JOIN centros c ON c.unidad_organizativa_id = h.unidad_organizativa_id AND c.deleted_at IS NULL
             WHERE h.id = p.historia_id
               AND p.centro_id IS NULL
        ');

        DB::statement("
            CREATE UNIQUE INDEX planes_intervencion_un_vigente_por_tipo
                ON planes_intervencion (historia_id, tipo_plan_id)
             WHERE estado IN ('borrador', 'activo')
               AND deleted_at IS NULL
               AND historia_id IS NOT NULL
               AND tipo_plan_id IS NOT NULL
        ");
    }

    /**
     * Quita el índice y la columna.
     *
     * @return void
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS planes_intervencion_un_vigente_por_tipo');

        Schema::table('planes_intervencion', function (Blueprint $table) {
            $table->dropConstrainedForeignId('centro_id');
        });
    }
};
