<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resto del modelo de datos de citas:
 * - `cita_acompanantes` y su catálogo de relaciones;
 * - ciudadanos referenciados por eventos de agenda (`evento_ciudadano`);
 * - enlace de apuntes y registros de atención con la cita que completan;
 * - configuración por centro (plazos por urgencia, aviso de cierre al supervisor);
 * - retirada de `tipos_slot.genera_apunte_automatico`: ahora es el apunte el que
 *   completa la cita (decisión del desarrollador, 2026-09-30).
 */
return new class extends Migration
{
    /** Relaciones del acompañante (docs/modulo-citas.md §2.5). */
    private const RELACIONES = [
        'representante_legal' => 'Representante legal',
        'tutor' => 'Tutor/a',
        'familiar' => 'Familiar',
        'unidad_convivencia' => 'Miembro de la unidad de convivencia',
        'persona_apoyo' => 'Persona de apoyo',
        'interprete' => 'Intérprete',
        'otra' => 'Otra',
    ];

    /**
     * Aplica los cambios.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('cita_acompanantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cita_id')->constrained('citas')->restrictOnDelete();
            $table->string('relacion', 50)->comment("catalogos_sistema, grupo 'cita.relacion_acompanante'");
            $table->foreignId('ciudadano_id')->nullable()->constrained('ciudadanos')->restrictOnDelete();
            $table->text('nombre')->nullable()->comment('Cifrado. Si no está en VIDA');
            $table->foreignId('registrado_por_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index('cita_id');
        });
        DB::statement('ALTER TABLE cita_acompanantes ADD CONSTRAINT cita_acompanantes_persona_check CHECK (ciudadano_id IS NOT NULL OR nombre IS NOT NULL)');

        Schema::create('evento_ciudadano', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_agenda_id')->constrained('eventos_agenda')->cascadeOnDelete();
            $table->foreignId('ciudadano_id')->constrained('ciudadanos')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['evento_agenda_id', 'ciudadano_id']);
        });

        Schema::table('plan_apuntes', function (Blueprint $table) {
            $table->foreignId('cita_id')->nullable()->constrained('citas')->restrictOnDelete();
            $table->foreignId('evento_agenda_id')->nullable()->constrained('eventos_agenda')->restrictOnDelete();
            $table->index('cita_id');
        });

        Schema::table('registros_atencion', function (Blueprint $table) {
            $table->foreignId('cita_id')->nullable()->constrained('citas')->restrictOnDelete()
                ->comment('Cita que completa. Distinto de cita_generada_id');
        });

        Schema::table('horarios_centro', function (Blueprint $table) {
            $table->jsonb('plazos_urgencia')->nullable()->comment('Días laborables por urgencia');
            $table->unsignedSmallInteger('dias_aviso_cierre_supervisor')->default(3);
        });
        DB::table('horarios_centro')->update(['plazos_urgencia' => json_encode(['ordinaria' => 20, 'preferente' => 7, 'urgente' => 2])]);

        Schema::table('tipos_slot', function (Blueprint $table) {
            $table->dropColumn('genera_apunte_automatico');
        });

        $ahora = now();
        $orden = 1;
        foreach (self::RELACIONES as $clave => $etiqueta) {
            DB::table('catalogos_sistema')->updateOrInsert(
                ['grupo' => 'cita.relacion_acompanante', 'clave' => $clave],
                ['etiqueta' => $etiqueta, 'orden' => $orden++, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            );
        }
    }

    /**
     * Revierte los cambios.
     *
     * @return void
     */
    public function down(): void
    {
        DB::table('catalogos_sistema')->where('grupo', 'cita.relacion_acompanante')->delete();

        Schema::table('tipos_slot', function (Blueprint $table) {
            $table->boolean('genera_apunte_automatico')->default(false);
        });
        Schema::table('horarios_centro', function (Blueprint $table) {
            $table->dropColumn(['plazos_urgencia', 'dias_aviso_cierre_supervisor']);
        });
        Schema::table('registros_atencion', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cita_id');
        });
        Schema::table('plan_apuntes', function (Blueprint $table) {
            $table->dropIndex(['cita_id']);
            $table->dropConstrainedForeignId('cita_id');
            $table->dropConstrainedForeignId('evento_agenda_id');
        });
        Schema::dropIfExists('evento_ciudadano');
        Schema::dropIfExists('cita_acompanantes');
    }
};
