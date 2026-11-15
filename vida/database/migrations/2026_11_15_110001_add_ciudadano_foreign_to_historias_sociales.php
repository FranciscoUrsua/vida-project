<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Añade la clave foránea de historias_sociales.ciudadano_id a ciudadanos.
 *
 * La migración original (2026_03_11_153335) dejó la columna sin FK porque el
 * módulo Ciudadanía aún no existía. Sin ella se podían crear historias de
 * personas inexistentes, que luego rompían la FK de audits.ciudadano_id al
 * auditar su edición. Los ciudadanos solo tienen baja lógica, así que el
 * borrado se restringe.
 *
 * @throws RuntimeException Si hay historias cuyo titular no existe
 */
return new class extends Migration
{
    /**
     * Comprueba que no hay historias huérfanas y crea la FK.
     *
     * @return void
     */
    public function up(): void
    {
        $huerfanas = DB::table('historias_sociales')
            ->leftJoin('ciudadanos', 'ciudadanos.id', '=', 'historias_sociales.ciudadano_id')
            ->whereNull('ciudadanos.id')
            ->count();

        // Se aborta con un mensaje claro en vez de dejar el error genérico de PostgreSQL.
        if ($huerfanas > 0) {
            throw new RuntimeException(
                "Hay {$huerfanas} historias sociales con un ciudadano_id que no existe en ciudadanos: corregirlas antes de migrar."
            );
        }

        Schema::table('historias_sociales', function (Blueprint $table) {
            $table->foreign('ciudadano_id')
                ->references('id')
                ->on('ciudadanos')
                ->restrictOnDelete();
        });
    }

    /**
     * Retira la FK.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('historias_sociales', function (Blueprint $table) {
            $table->dropForeign(['ciudadano_id']);
        });
    }
};
