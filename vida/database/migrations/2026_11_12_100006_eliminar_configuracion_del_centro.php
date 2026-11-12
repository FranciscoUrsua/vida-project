<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina del centro lo que ha pasado al tipo (addenda D2, §6.2: «el tipo manda»).
 *
 * Tras la migración de datos (2026_11_12_100004) nada lee estas columnas y
 * tablas fuera de las migraciones (comprobado con grep; ver CHANGELOG). El tipo
 * pasa a ser obligatorio en centros, prestaciones y asignaciones. El `down()`
 * recrea las columnas y tablas vacías.
 */
return new class extends Migration
{
    /**
     * Elimina columnas y tablas y fija los NOT NULL.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            $table->dropIndex(['tipo_centro']);
            $table->dropColumn(['inscripcion_libre', 'tipo_centro', 'modo_asignacion_referencia', 'ventana_reparto_meses', 'meses_inactividad_caso', 'horario']);
        });

        Schema::dropIfExists('centro_segmento_poblacion');
        Schema::dropIfExists('centro_prestacion');

        Schema::table('prestacion_tipo_centro', function (Blueprint $table) {
            $table->dropUnique(['prestacion_id', 'tipo_centro']);
            $table->dropIndex(['tipo_centro']);
            $table->dropColumn('tipo_centro');
        });
        Schema::table('asignaciones_centro', function (Blueprint $table) {
            $table->dropColumn('tipo_centro');
        });

        // Todo centro, catálogo de prestaciones y asignación tiene tipo (RN-01)
        DB::statement('ALTER TABLE centros ALTER COLUMN tipo_centro_id SET NOT NULL');
        DB::statement('ALTER TABLE prestacion_tipo_centro ALTER COLUMN tipo_centro_id SET NOT NULL');
        DB::statement('ALTER TABLE asignaciones_centro ALTER COLUMN tipo_centro_id SET NOT NULL');

        // Las claves del catálogo antiguo se conservan, inactivas (paso 2)
        DB::table('catalogos_sistema')->where('grupo', 'centro.tipo')->update(['activo' => false]);
    }

    /**
     * Recrea vacías las columnas y tablas eliminadas.
     *
     * @return void
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE centros ALTER COLUMN tipo_centro_id DROP NOT NULL');
        DB::statement('ALTER TABLE prestacion_tipo_centro ALTER COLUMN tipo_centro_id DROP NOT NULL');
        DB::statement('ALTER TABLE asignaciones_centro ALTER COLUMN tipo_centro_id DROP NOT NULL');

        Schema::table('centros', function (Blueprint $table) {
            $table->boolean('inscripcion_libre')->default(false);
            $table->string('tipo_centro', 100)->nullable()->index();
            $table->string('modo_asignacion_referencia', 20)->default('sorteo');
            $table->unsignedSmallInteger('ventana_reparto_meses')->default(12);
            $table->unsignedSmallInteger('meses_inactividad_caso')->default(6);
            $table->json('horario')->nullable();
        });
        Schema::create('centro_segmento_poblacion', function (Blueprint $table) {
            $table->foreignId('centro_id')->constrained('centros')->cascadeOnDelete();
            $table->foreignId('segmento_poblacion_id')->constrained('segmentos_poblacion')->cascadeOnDelete();
            $table->primary(['centro_id', 'segmento_poblacion_id']);
        });
        Schema::create('centro_prestacion', function (Blueprint $table) {
            $table->foreignId('centro_id')->constrained('centros')->cascadeOnDelete();
            $table->foreignId('prestacion_id')->constrained('prestaciones')->cascadeOnDelete();
            $table->primary(['centro_id', 'prestacion_id']);
        });
        Schema::table('prestacion_tipo_centro', function (Blueprint $table) {
            $table->string('tipo_centro', 100)->nullable()->index();
        });
        Schema::table('asignaciones_centro', function (Blueprint $table) {
            $table->string('tipo_centro', 100)->nullable();
        });
    }
};
