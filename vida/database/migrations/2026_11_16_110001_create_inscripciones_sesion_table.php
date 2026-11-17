<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inscripción de personas en sesiones de actividad (fase 6, paso 1).
 *
 * - `inscripciones_sesion`: una fila por persona y sesión, por vía libre o por
 *   prescripción. Nada se borra (F6-5): la baja es lógica, con motivo, y un trigger
 *   rechaza el DELETE. Solo puede haber una inscripción vigente por persona y sesión
 *   (índice único parcial sobre las `inscrita`).
 * - `actividades.prestacion_id`: prestación del catálogo vinculada; al inscribir se
 *   crea la prestación de la persona (fase 3 bis). `actividades.motivo_baja`: la baja
 *   de una actividad lleva fecha y motivo.
 * - `sesiones_actividad`: motivo, fecha y autor de la cancelación.
 *
 * Datos previos (F6-9): cada prescripción `activa` a una sesión `programada` recibe su
 * inscripción por vía `prescripcion`, inscrita por el usuario del profesional que
 * prescribió (en la BD compartida, 81, todas de mundos demo).
 */
return new class extends Migration
{
    /**
     * Crea la tabla, las columnas nuevas y migra las prescripciones activas.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('inscripciones_sesion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_actividad_id')->constrained('sesiones_actividad');
            $table->foreignId('ciudadano_id')->constrained('ciudadanos');
            $table->string('via', 20)->comment('libre | prescripcion');
            $table->foreignId('prescripcion_id')->nullable()->constrained('prescripciones');
            $table->string('estado', 20)->default('inscrita')->comment('inscrita | baja');
            $table->foreignId('inscrita_por_id')->constrained('users');
            $table->timestamp('fecha_baja')->nullable();
            $table->text('motivo_baja')->nullable();
            $table->foreignId('baja_por_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['ciudadano_id', 'estado']);
            $table->index(['sesion_actividad_id', 'estado', 'via']);
        });

        DB::statement("ALTER TABLE inscripciones_sesion ADD CONSTRAINT inscripciones_sesion_via_check CHECK (via IN ('libre', 'prescripcion'))");
        DB::statement("ALTER TABLE inscripciones_sesion ADD CONSTRAINT inscripciones_sesion_estado_check CHECK (estado IN ('inscrita', 'baja'))");
        DB::statement("ALTER TABLE inscripciones_sesion ADD CONSTRAINT inscripciones_sesion_prescripcion_check CHECK (via <> 'prescripcion' OR prescripcion_id IS NOT NULL)");
        DB::statement("ALTER TABLE inscripciones_sesion ADD CONSTRAINT inscripciones_sesion_baja_check CHECK (estado <> 'baja' OR (motivo_baja IS NOT NULL AND fecha_baja IS NOT NULL))");
        DB::statement("CREATE UNIQUE INDEX inscripciones_sesion_vigente_unique ON inscripciones_sesion (sesion_actividad_id, ciudadano_id) WHERE estado = 'inscrita'");

        // Nada se borra (F6-5): la baja es lógica
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION inscripciones_sesion_sin_borrado() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Las inscripciones en sesiones no se borran: se dan de baja.';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER inscripciones_sesion_sin_borrado
                BEFORE DELETE ON inscripciones_sesion
                FOR EACH ROW EXECUTE FUNCTION inscripciones_sesion_sin_borrado();
            SQL);

        Schema::table('actividades', function (Blueprint $table) {
            $table->foreignId('prestacion_id')->nullable()->after('tipo_actividad_id')->constrained('prestaciones');
            $table->text('motivo_baja')->nullable()->after('fecha_baja');
        });

        Schema::table('sesiones_actividad', function (Blueprint $table) {
            $table->text('motivo_cancelacion')->nullable()->after('estado');
            $table->timestamp('fecha_cancelacion')->nullable()->after('motivo_cancelacion');
            $table->foreignId('cancelada_por_id')->nullable()->after('fecha_cancelacion')->constrained('users');
        });

        $this->migrarPrescripcionesActivas();
    }

    /**
     * Quita la tabla y las columnas nuevas.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('sesiones_actividad', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelada_por_id');
            $table->dropColumn(['motivo_cancelacion', 'fecha_cancelacion']);
        });

        Schema::table('actividades', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prestacion_id');
            $table->dropColumn('motivo_baja');
        });

        Schema::dropIfExists('inscripciones_sesion');
        DB::unprepared('DROP FUNCTION IF EXISTS inscripciones_sesion_sin_borrado()');
    }

    /**
     * Inscripción por vía `prescripcion` de cada prescripción activa a una sesión
     * programada (F6-9). La inscribe el usuario del profesional que prescribió.
     *
     * @return void
     */
    private function migrarPrescripcionesActivas(): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO inscripciones_sesion
                (sesion_actividad_id, ciudadano_id, via, prescripcion_id, estado, inscrita_por_id, created_at, updated_at)
            SELECT p.destino_id, p.ciudadano_id, 'prescripcion', p.id, 'inscrita',
                   (SELECT u.id FROM users u WHERE u.profesional_id = p.profesional_id ORDER BY u.id LIMIT 1),
                   COALESCE(p.created_at, NOW()), NOW()
            FROM prescripciones p
            JOIN sesiones_actividad s ON s.id = p.destino_id AND s.deleted_at IS NULL
            WHERE p.tipo_destino = 'sesion_actividad'
              AND p.estado = 'activa'
              AND p.deleted_at IS NULL
              AND s.estado = 'programada'
              AND EXISTS (SELECT 1 FROM users u WHERE u.profesional_id = p.profesional_id)
            SQL);
    }
};
