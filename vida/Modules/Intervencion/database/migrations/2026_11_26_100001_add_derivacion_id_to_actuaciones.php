<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo de las actuaciones al encargo profesional (EA-5 de
 * docs/instrucciones-cli/2026-11-encargo-actuaciones.md).
 *
 * Los apuntes, las fichas de valoración, los pases de escala y los informes que registra el
 * profesional de encargo llevan el encargo en `derivacion_id`. Es lo que decide qué ve ese
 * profesional (EA-7: solo lo suyo dentro de sus encargos) y qué centro cuenta para RN-15 (el
 * del encargo, D1.6). Nullable: lo registrado desde la Historia o el plan no tiene encargo.
 */
return new class extends Migration
{
    /** Tablas de las actuaciones que se pueden registrar desde un encargo. */
    private const TABLAS = ['plan_apuntes', 'fichas', 'pases_escala', 'informes'];

    /**
     * Añade `derivacion_id` con su clave foránea e índice.
     *
     * @return void
     */
    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->foreignId('derivacion_id')->nullable()->constrained('derivaciones');
            });
        }
    }

    /**
     * Retira `derivacion_id`.
     *
     * @return void
     */
    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropConstrainedForeignId('derivacion_id');
            });
        }
    }
};
