<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitudes de prestación, su historial, la resolución y las prestaciones de la persona
 * (fase 3 bis, paso 2; docs/modulo-solicitudes-expedientes.md §2.2 a §2.6).
 *
 * Los historiales (`solicitud_prestacion_eventos`, `prestacion_persona_eventos`) son de solo
 * inserción: un trigger rechaza UPDATE y DELETE (principio 4.2, RN-11). Los textos libres
 * sobre la persona (motivos, notas) se guardan cifrados desde los modelos, por eso son `text`.
 */
return new class extends Migration
{
    /**
     * Crea las tablas, los triggers y los catálogos del sistema.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('solicitudes_prestacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciudadano_id')->constrained('ciudadanos');
            $table->foreignId('historia_id')->constrained('historias_sociales');
            $table->foreignId('prestacion_id')->constrained('prestaciones');
            $table->jsonb('configuracion_snapshot');
            $table->string('via', 30);
            $table->foreignId('plan_id')->nullable()->constrained('planes_intervencion');
            $table->foreignId('plan_objetivo_id')->nullable()->constrained('plan_objetivos');
            $table->foreignId('seguimiento_id')->nullable()->constrained('seguimientos_plan');
            $table->foreignId('centro_solicitud_id')->constrained('centros');
            $table->foreignId('unidad_tramitadora_id')->nullable()->constrained('unidades_organizativas');
            $table->foreignId('creada_por_id')->constrained('users');
            $table->foreignId('sugerida_id')->nullable()->constrained('users');
            $table->foreignId('responsable_id')->nullable()->constrained('users');
            $table->string('estado', 30)->default('borrador');
            $table->foreignId('ficha_id')->nullable()->constrained('fichas');
            $table->string('numero_expediente', 100)->nullable();
            $table->date('fecha_envio')->nullable();
            $table->string('estado_externo_codigo', 100)->nullable();
            $table->string('estado_externo_texto')->nullable();
            $table->string('estado_externo_normalizado', 50)->nullable();
            $table->timestamp('ultima_actualizacion_en')->nullable();
            $table->text('motivo_cierre')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['unidad_tramitadora_id', 'estado']);
            $table->index('ciudadano_id');
        });

        DB::statement("ALTER TABLE solicitudes_prestacion ADD CONSTRAINT solicitudes_prestacion_estado_check CHECK (estado IN ('borrador','en_bandeja','en_preparacion','enviada','resuelta','cerrada_sin_resolucion','anulada'))");
        DB::statement("ALTER TABLE solicitudes_prestacion ADD CONSTRAINT solicitudes_prestacion_via_check CHECK (via IN ('tramitacion_interna','expediente_externo'))");

        Schema::create('solicitud_prestacion_personas', function (Blueprint $table) {
            $table->foreignId('solicitud_id')->constrained('solicitudes_prestacion')->cascadeOnDelete();
            $table->foreignId('ciudadano_id')->constrained('ciudadanos');
            $table->primary(['solicitud_id', 'ciudadano_id']);
        });

        Schema::create('solicitud_prestacion_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes_prestacion');
            $table->string('accion', 40);
            $table->string('estado_antes', 30)->nullable();
            $table->string('estado_despues', 30)->nullable();
            $table->string('origen', 30);
            $table->foreignId('usuario_id')->nullable()->constrained('users');
            $table->jsonb('detalle')->nullable();
            $table->text('nota')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('solicitud_id');
        });

        Schema::create('resoluciones_solicitud', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->unique()->constrained('solicitudes_prestacion');
            $table->date('fecha');
            $table->string('sentido', 20);
            $table->string('origen', 30);
            $table->foreignId('registrada_por_id')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('resolucion_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resolucion_id')->constrained('resoluciones_solicitud')->cascadeOnDelete();
            $table->foreignId('prestacion_id')->constrained('prestaciones');
            $table->boolean('concedida');
            $table->text('motivo_denegacion')->nullable();
            $table->decimal('importe', 12, 2)->nullable();
            $table->string('intensidad', 100)->nullable();
            $table->date('fecha_desde')->nullable();
            $table->date('fecha_hasta')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        Schema::create('prestaciones_persona', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciudadano_id')->constrained('ciudadanos');
            $table->foreignId('prestacion_id')->constrained('prestaciones');
            $table->nullableMorphs('origen');
            $table->foreignId('plan_id')->nullable()->constrained('planes_intervencion');
            $table->foreignId('plan_objetivo_id')->nullable()->constrained('plan_objetivos');
            $table->string('estado', 20);
            $table->date('fecha_concesion')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->text('motivo_suspension')->nullable();
            $table->text('motivo_fin')->nullable();
            $table->jsonb('condiciones')->nullable();
            $table->foreignId('registrada_por_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['ciudadano_id', 'estado']);
        });

        DB::statement("ALTER TABLE prestaciones_persona ADD CONSTRAINT prestaciones_persona_estado_check CHECK (estado IN ('concedida','activa','suspendida','finalizada'))");

        Schema::create('prestacion_persona_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestacion_persona_id')->constrained('prestaciones_persona');
            $table->foreignId('usuario_id')->nullable()->constrained('users');
            $table->string('estado_antes', 20)->nullable();
            $table->string('estado_despues', 20);
            $table->text('motivo')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('prestacion_persona_id');
        });

        foreach (['solicitud_prestacion_eventos', 'prestacion_persona_eventos'] as $tabla) {
            DB::statement(<<<SQL
                CREATE OR REPLACE FUNCTION {$tabla}_solo_insercion() RETURNS trigger AS \$\$
                BEGIN
                    RAISE EXCEPTION '{$tabla} es de solo inserción: el historial no se modifica ni se borra';
                END;
                \$\$ LANGUAGE plpgsql
                SQL);
            DB::statement("CREATE TRIGGER {$tabla}_inmutable BEFORE UPDATE OR DELETE ON {$tabla} FOR EACH ROW EXECUTE FUNCTION {$tabla}_solo_insercion()");
        }

        // Prestación de una prescripción de plaza nacida de «Solicitar prestación» (decisión del
        // 15 nov 2026): al asignarse la plaza se crea la prestación de la persona activa.
        Schema::table('prescripciones', function (Blueprint $table) {
            $table->foreignId('prestacion_id')->nullable()->after('ciudadano_id')->constrained('prestaciones');
        });

        // Fuente de los apuntes automáticos (RN-09): `gestor_expedientes` o `registro_manual`.
        Schema::table('plan_apuntes', function (Blueprint $table) {
            $table->string('fuente', 30)->nullable()->after('visibilidad');
        });

        $this->catalogos();
    }

    /**
     * Elimina triggers, tablas y catálogos.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('prescripciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prestacion_id');
        });

        Schema::table('plan_apuntes', function (Blueprint $table) {
            $table->dropColumn('fuente');
        });

        foreach (['solicitud_prestacion_eventos', 'prestacion_persona_eventos'] as $tabla) {
            DB::statement("DROP TRIGGER IF EXISTS {$tabla}_inmutable ON {$tabla}");
            DB::statement("DROP FUNCTION IF EXISTS {$tabla}_solo_insercion()");
        }

        foreach (['prestacion_persona_eventos', 'prestaciones_persona', 'resolucion_lineas', 'resoluciones_solicitud',
            'solicitud_prestacion_eventos', 'solicitud_prestacion_personas', 'solicitudes_prestacion'] as $tabla) {
            Schema::dropIfExists($tabla);
        }

        DB::table('catalogos_sistema')->whereIn('grupo', ['expediente.estado', 'expediente.incidencia', 'prestacion_persona.motivo_fin', 'prestacion_persona.motivo_suspension'])->delete();
    }

    /**
     * Catálogos del sistema del módulo (estados normalizados, incidencias y motivos).
     *
     * @return void
     */
    private function catalogos(): void
    {
        $grupos = [
            'expediente.estado' => ['en_tramite' => 'En trámite', 'incidencia' => 'Con incidencia', 'resuelta' => 'Resuelta', 'desistida' => 'Desistida', 'archivada' => 'Archivada'],
            'expediente.incidencia' => ['requerimiento_documentacion' => 'Requerimiento de documentación', 'subsanacion' => 'Subsanación', 'audiencia' => 'Trámite de audiencia', 'otra' => 'Otra'],
            'prestacion_persona.motivo_fin' => ['fin_periodo' => 'Fin del periodo concedido', 'renuncia' => 'Renuncia de la persona', 'objetivos_cumplidos' => 'Objetivos cumplidos', 'cambio_circunstancias' => 'Cambio de circunstancias', 'fallecimiento' => 'Fallecimiento', 'otro' => 'Otro'],
            'prestacion_persona.motivo_suspension' => ['ingreso_hospitalario' => 'Ingreso hospitalario', 'ausencia_temporal' => 'Ausencia temporal', 'incumplimiento' => 'Incumplimiento de condiciones', 'otro' => 'Otro'],
        ];

        foreach ($grupos as $grupo => $valores) {
            $orden = 0;
            foreach ($valores as $clave => $etiqueta) {
                DB::table('catalogos_sistema')->updateOrInsert(
                    ['grupo' => $grupo, 'clave' => $clave],
                    ['etiqueta' => $etiqueta, 'orden' => ++$orden, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
                );
            }
        }
    }
};
