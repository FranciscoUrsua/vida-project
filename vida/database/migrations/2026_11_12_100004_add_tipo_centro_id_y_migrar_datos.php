<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Centro\Support\MigracionTipoCentro;

/**
 * Tipo de centro en centros, prestaciones y asignaciones (paso 2) y migración
 * de la configuración del centro al tipo (paso 3, addenda D2).
 *
 * Las columnas se crean nullable y se rellenan aquí; pasan a NOT NULL, y las
 * columnas antiguas del centro se eliminan, en migraciones posteriores de la fase.
 * También fija la integridad UO–centro (RN-14): una UO tiene como mucho un centro.
 */
return new class extends Migration
{
    /**
     * Añade las columnas, ejecuta la migración de datos y rehace los índices.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            $table->foreignId('tipo_centro_id')->nullable()->after('tipo_gestion')->constrained('tipos_centro')->restrictOnDelete();
            $table->foreignId('centro_antecesor_id')->nullable()->after('tipo_centro_id')->constrained('centros')->restrictOnDelete();
        });

        Schema::table('prestacion_tipo_centro', function (Blueprint $table) {
            $table->foreignId('tipo_centro_id')->nullable()->after('prestacion_id')->constrained('tipos_centro')->cascadeOnDelete();
        });

        Schema::table('asignaciones_centro', function (Blueprint $table) {
            $table->foreignId('tipo_centro_id')->nullable()->after('ciudadano_id')->constrained('tipos_centro')->restrictOnDelete();
        });

        $informe = app(MigracionTipoCentro::class)->ejecutar();
        // El informe de descartes se copia al CHANGELOG (addenda §6.2); aquí queda en el log del despliegue
        Log::info('Migración Tipo de Centro', $informe);

        Schema::table('prestacion_tipo_centro', function (Blueprint $table) {
            $table->unique(['prestacion_id', 'tipo_centro_id']);
        });

        DB::statement('DROP INDEX IF EXISTS asignaciones_centro_vigente_por_tipo');
        DB::statement(
            'CREATE UNIQUE INDEX asignaciones_centro_vigente_por_tipo ON asignaciones_centro (ciudadano_id, tipo_centro_id) '
            .'WHERE fecha_fin IS NULL AND deleted_at IS NULL'
        );

        // RN-14: una UO tiene como mucho un centro (vivo)
        DB::statement(
            'CREATE UNIQUE INDEX centros_una_por_uo ON centros (unidad_organizativa_id) '
            .'WHERE unidad_organizativa_id IS NOT NULL AND deleted_at IS NULL'
        );
    }

    /**
     * Quita las columnas e índices; los tipos y denominaciones se borran con sus tablas.
     *
     * @return void
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS centros_una_por_uo');
        DB::statement('DROP INDEX IF EXISTS asignaciones_centro_vigente_por_tipo');
        DB::statement(
            'CREATE UNIQUE INDEX asignaciones_centro_vigente_por_tipo ON asignaciones_centro (ciudadano_id, tipo_centro) '
            .'WHERE fecha_fin IS NULL AND deleted_at IS NULL'
        );

        Schema::table('prestacion_tipo_centro', function (Blueprint $table) {
            $table->dropUnique(['prestacion_id', 'tipo_centro_id']);
            $table->dropConstrainedForeignId('tipo_centro_id');
        });
        Schema::table('asignaciones_centro', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tipo_centro_id');
        });
        Schema::table('centros', function (Blueprint $table) {
            $table->dropConstrainedForeignId('centro_antecesor_id');
            $table->dropConstrainedForeignId('tipo_centro_id');
        });
        DB::table('denominaciones')->delete();
        DB::table('tipo_centro_segmento_poblacion')->delete();
        DB::table('tipos_centro')->delete();
    }
};
