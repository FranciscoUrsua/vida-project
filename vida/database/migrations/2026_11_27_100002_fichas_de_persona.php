<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fichas de la persona (SD-7 y SD-9 de la ficha sociodemográfica): `ciudadano_id` para las
 * fichas que pertenecen al ciudadano y no a una Historia Social, y `no_contesta` con los
 * campos que la persona no ha querido contestar.
 *
 * El `CHECK` no puede leer el uso del tipo de ficha (otra tabla): garantiza que una ficha
 * con `ciudadano_id` no cuelga de una Historia, una valoración ni un encargo. Que una ficha
 * de uso persona tenga `ciudadano_id` lo garantiza `FichaPersonaService`.
 *
 * @see docs/instrucciones-cli/2026-11-ficha-sociodemografica.md, bloque C
 */
return new class extends Migration
{
    /**
     * Añade las columnas y el CHECK.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('fichas', function (Blueprint $table) {
            $table->foreignId('ciudadano_id')->nullable()->after('historia_id')
                ->constrained('ciudadanos')->restrictOnDelete();
            $table->jsonb('no_contesta')->nullable()->after('datos')
                ->comment('campo_id que la persona no ha querido contestar (SD-9)');
            $table->index(['ciudadano_id', 'tipo_ficha_id']);
        });

        DB::statement('ALTER TABLE fichas ADD CONSTRAINT fichas_persona_sin_historia CHECK (
            ciudadano_id IS NULL OR (historia_id IS NULL AND valoracion_id IS NULL AND derivacion_id IS NULL)
        )');
    }

    /**
     * Retira el CHECK y las columnas.
     *
     * @return void
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE fichas DROP CONSTRAINT IF EXISTS fichas_persona_sin_historia');

        Schema::table('fichas', function (Blueprint $table) {
            $table->dropIndex(['ciudadano_id', 'tipo_ficha_id']);
            $table->dropConstrainedForeignId('ciudadano_id');
            $table->dropColumn('no_contesta');
        });
    }
};
