<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Acceso y tramitación de las prestaciones (fase 3 bis, paso 1; docs/modulo-solicitudes-expedientes.md §2.1).
 *
 * - `prestaciones`: unidad tramitadora (`uo` / `centro_solicitud`), formulario de solicitud
 *   (un `TipoFicha` de uso `solicitud`) y código en el gestor de expedientes.
 * - `tipo_fichas.uso`: separa los formularios de solicitud de las fichas de valoración.
 * - `prestacion_documentos_exigidos`: documentos que pide cada prestación.
 * - Vía de acceso inicial de las prestaciones existentes, con el mapeo confirmado por el
 *   desarrollador el 15 nov 2026: económicas, SAD, SAD intensivo, teleasistencia y ayuda a
 *   domicilio para familias → expediente externo; alojamientos, residencias, centros de día y
 *   acogidas → plaza; el resto → directa. Las de tramitación quedan con unidad «centro de
 *   solicitud» hasta que se configure otra en el panel.
 */
return new class extends Migration
{
    /** Códigos del catálogo con vía «expediente externo» además de las económicas. */
    private const EXTERNAS = ['040101', '040102', '040301', '030102'];

    /** Códigos del catálogo con vía «plaza». */
    private const PLAZAS = ['020102', '020103', '020104', '030203', '050101', '050102', '050201', '050301', '060202', '060203'];

    /**
     * Añade columnas, tabla y datos iniciales.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('prestaciones', function (Blueprint $table) {
            $table->string('unidad_tramitadora_tipo', 20)->nullable()->after('tipo_plan_derivacion_id');
            $table->foreignId('unidad_tramitadora_id')->nullable()->after('unidad_tramitadora_tipo')->constrained('unidades_organizativas');
            $table->foreignId('formulario_solicitud_id')->nullable()->after('unidad_tramitadora_id')->constrained('tipo_fichas');
            $table->string('codigo_gestor', 100)->nullable()->after('formulario_solicitud_id');
        });

        Schema::table('tipo_fichas', function (Blueprint $table) {
            $table->string('uso', 20)->default('valoracion')->after('nombre');
        });

        Schema::create('prestacion_documentos_exigidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestacion_id')->constrained('prestaciones')->cascadeOnDelete();
            $table->foreignId('tipo_documental_id')->constrained('tipos_documentales');
            $table->boolean('obligatorio')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
            $table->unique(['prestacion_id', 'tipo_documental_id']);
        });

        // Vía inicial solo donde no la haya (las de derivación de la fase 3 se respetan).
        $sinVia = fn () => DB::table('prestaciones')->whereNull('via_acceso');
        $sinVia()->where(fn ($q) => $q->where('tipo_prestacion', 'economica')->orWhereIn('codigo', self::EXTERNAS))
            ->update(['via_acceso' => 'expediente_externo', 'unidad_tramitadora_tipo' => 'centro_solicitud']);
        $sinVia()->whereIn('codigo', self::PLAZAS)->update(['via_acceso' => 'plaza']);
        $sinVia()->update(['via_acceso' => 'directa']);
    }

    /**
     * Quita tabla y columnas (la vía inicial no se deshace).
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('prestacion_documentos_exigidos');

        Schema::table('tipo_fichas', function (Blueprint $table) {
            $table->dropColumn('uso');
        });

        Schema::table('prestaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('formulario_solicitud_id');
            $table->dropConstrainedForeignId('unidad_tramitadora_id');
            $table->dropColumn(['unidad_tramitadora_tipo', 'codigo_gestor']);
        });
    }
};
