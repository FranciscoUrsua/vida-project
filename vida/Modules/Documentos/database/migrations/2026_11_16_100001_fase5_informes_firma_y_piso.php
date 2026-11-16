<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5 (docs/instrucciones-cli/2026-11-fase5-informes.md):
 *
 * - `informes.firmante_certificado`: titular del certificado con el que se firmó (F5-2),
 *   y `deleted_at`: un borrador eliminado por su autor queda con baja lógica.
 * - `piso_firmados`: un plan conserva todos sus PISO firmados y solo uno activo (F5-4).
 *   El `unique` por plan pasa a índice único parcial sobre los no sustituidos, la FK al
 *   plan se crea (la tabla ya existe) y el documento deja de borrarse en cascada
 *   (no hay borrados físicos de documentos custodiados).
 * - Tipo documental `piso_firmado`, vinculable a la persona y al plan.
 */
return new class extends Migration
{
    /**
     * Aplica los cambios.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('informes', function (Blueprint $table) {
            $table->text('firmante_certificado')->nullable()->after('metodo_firma');
            // Los borradores se pueden eliminar (no son documentos custodiados), con baja lógica.
            $table->softDeletes();
        });

        Schema::table('piso_firmados', function (Blueprint $table) {
            $table->dropUnique(['plan_de_intervencion_id']);
            $table->dropForeign(['documento_id']);
            $table->timestamp('sustituido_en')->nullable()->after('observaciones');
        });

        Schema::table('piso_firmados', function (Blueprint $table) {
            $table->foreign('plan_de_intervencion_id')->references('id')->on('planes_intervencion')->restrictOnDelete();
            $table->foreign('documento_id')->references('id')->on('documentos')->restrictOnDelete();
        });

        // Un solo PISO firmado activo por plan; los sustituidos se conservan.
        DB::statement('CREATE UNIQUE INDEX piso_firmados_plan_activo_unique ON piso_firmados (plan_de_intervencion_id) WHERE sustituido_en IS NULL');

        if (! DB::table('tipos_documentales')->where('codigo', 'piso_firmado')->exists()) {
            DB::table('tipos_documentales')->insert([
                'codigo' => 'piso_firmado',
                'nombre' => 'PISO firmado',
                'familia' => 'informe_profesional',
                'origen_eni' => 'administracion',
                'caduca' => false,
                'politica_versiones' => 'conservar',
                'conservacion_anyos' => null,
                'visible_ciudadano_defecto' => false,
                'requiere_firma' => true,
                'max_bytes' => (int) config('documentos.max_bytes_defecto'),
                'max_paginas' => (int) config('documentos.max_paginas_defecto'),
                'metadatos_requeridos' => json_encode([]),
                'vinculables' => json_encode(['ciudadano', 'intervencion']),
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Revierte los cambios (el tipo documental se conserva si ya tiene documentos).
     *
     * @return void
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS piso_firmados_plan_activo_unique');

        Schema::table('piso_firmados', function (Blueprint $table) {
            $table->dropForeign(['plan_de_intervencion_id']);
            $table->dropForeign(['documento_id']);
            $table->dropColumn('sustituido_en');
        });

        Schema::table('piso_firmados', function (Blueprint $table) {
            $table->unique('plan_de_intervencion_id');
            $table->foreign('documento_id')->references('id')->on('documentos')->cascadeOnDelete();
        });

        Schema::table('informes', function (Blueprint $table) {
            $table->dropColumn('firmante_certificado');
            $table->dropSoftDeletes();
        });

        $tipo = DB::table('tipos_documentales')->where('codigo', 'piso_firmado')->first();
        if ($tipo !== null && ! DB::table('documentos')->where('tipo_documental_id', $tipo->id)->exists()) {
            DB::table('tipos_documentales')->where('id', $tipo->id)->delete();
        }
    }
};
