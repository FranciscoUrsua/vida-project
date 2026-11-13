<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Añade a los centros la configuración de asignación de personas y de
 * profesional de referencia (docs/modulo-asignacion.md §8).
 *
 * - `tipo_centro`: clave del catálogo `centro.tipo`. Una persona tiene como
 *   máximo un centro asignado vigente por tipo (RN-02). Añade `ciam` al
 *   catálogo; el centro de servicios sociales es `css_general`, que ya existía.
 * - `modo_asignacion_referencia`: los centros existentes pasan a `quien_abre`
 *   para no cambiar el comportamiento de sus datos (RN-12); los nuevos
 *   nacen en `sorteo`.
 */
return new class extends Migration
{
    /**
     * Añade las columnas, migra los centros existentes y carga la clave `ciam`.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            $table->string('tipo_centro', 100)->nullable()->after('tipo_gestion')
                ->comment("Clave de catalogos_sistema, grupo 'centro.tipo'");
            $table->string('modo_asignacion_referencia', 20)->default('sorteo')
                ->comment('sorteo | libre_eleccion | quien_abre');
            $table->unsignedSmallInteger('ventana_reparto_meses')->default(12);
            $table->unsignedSmallInteger('meses_inactividad_caso')->default(6);

            $table->index('tipo_centro');
        });

        DB::table('centros')->update(['modo_asignacion_referencia' => 'quien_abre']);

        $ahora = now();
        DB::table('catalogos_sistema')->updateOrInsert(
            ['grupo' => 'centro.tipo', 'clave' => 'ciam'],
            ['etiqueta' => 'Centro Integral de Atención a la Mujer (CIAM)', 'orden' => 13, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
        );
    }

    /**
     * Elimina las columnas. La clave `ciam` del catálogo se conserva: puede estar en uso.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            $table->dropIndex(['tipo_centro']);
            $table->dropColumn(['tipo_centro', 'modo_asignacion_referencia', 'ventana_reparto_meses', 'meses_inactividad_caso']);
        });
    }
};
