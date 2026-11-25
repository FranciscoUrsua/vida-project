<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encargo profesional como modalidad de derivación (EP-1 y EP-4 de
 * docs/instrucciones-cli/2026-11-encargo-profesional.md).
 *
 * El encargo no abre plan especializado ni lleva prestación ni tipo de plan, y puede
 * nacer de la Historia Social sin plan: esas columnas pasan a nullable, pero un `CHECK`
 * las sigue exigiendo en las derivaciones a atención especializada. Nuevo estado
 * `cerrada` con el resultado del encargo.
 */
return new class extends Migration
{
    /**
     * Añade la modalidad, el estado `cerrada` y el resultado.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('derivaciones', function (Blueprint $table) {
            $table->string('modalidad', 20)->default('plan_especializado')->after('historia_id');
            $table->text('resultado')->nullable();
            $table->timestamp('cerrada_en')->nullable();
            $table->foreignId('cerrada_por_id')->nullable()->constrained('users');
            $table->index(['profesional_responsable_id', 'estado']);
        });

        DB::statement('ALTER TABLE derivaciones ALTER COLUMN plan_origen_id DROP NOT NULL');
        DB::statement('ALTER TABLE derivaciones ALTER COLUMN prestacion_id DROP NOT NULL');
        DB::statement('ALTER TABLE derivaciones ALTER COLUMN tipo_plan_id DROP NOT NULL');

        DB::statement("ALTER TABLE derivaciones ADD CONSTRAINT derivaciones_modalidad_check CHECK (modalidad IN ('plan_especializado', 'encargo'))");
        // La derivación a atención especializada sigue exigiendo plan de origen, prestación y tipo de plan
        DB::statement("ALTER TABLE derivaciones ADD CONSTRAINT derivaciones_plan_especializado_check CHECK (modalidad <> 'plan_especializado' OR (plan_origen_id IS NOT NULL AND prestacion_id IS NOT NULL AND tipo_plan_id IS NOT NULL))");

        DB::statement('ALTER TABLE derivaciones DROP CONSTRAINT derivaciones_estado_check');
        DB::statement("ALTER TABLE derivaciones ADD CONSTRAINT derivaciones_estado_check CHECK (estado IN ('pendiente', 'aceptada', 'rechazada', 'anulada', 'cerrada'))");
    }

    /**
     * Deshace los cambios (solo si no hay encargos).
     *
     * @return void
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE derivaciones DROP CONSTRAINT derivaciones_estado_check');
        DB::statement("ALTER TABLE derivaciones ADD CONSTRAINT derivaciones_estado_check CHECK (estado IN ('pendiente', 'aceptada', 'rechazada', 'anulada'))");
        DB::statement('ALTER TABLE derivaciones DROP CONSTRAINT derivaciones_plan_especializado_check');
        DB::statement('ALTER TABLE derivaciones DROP CONSTRAINT derivaciones_modalidad_check');

        DB::statement('ALTER TABLE derivaciones ALTER COLUMN plan_origen_id SET NOT NULL');
        DB::statement('ALTER TABLE derivaciones ALTER COLUMN prestacion_id SET NOT NULL');
        DB::statement('ALTER TABLE derivaciones ALTER COLUMN tipo_plan_id SET NOT NULL');

        Schema::table('derivaciones', function (Blueprint $table) {
            $table->dropIndex(['profesional_responsable_id', 'estado']);
            $table->dropConstrainedForeignId('cerrada_por_id');
            $table->dropColumn(['modalidad', 'resultado', 'cerrada_en']);
        });
    }
};
