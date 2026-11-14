<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrección de datos de la fase 3 (A1.6), con las decisiones del desarrollador del 14 nov 2026:
 *
 * 1. `tipo` coherente con el ámbito del tipo de plan en todos los planes que lo tienen.
 * 2. Los planes **activos** sin tipo de plan reciben uno: el PIA si la historia es de un
 *    centro de tipo `ciam` (lo que ofrece el CIAM; entrada directa, sin plan ASP), y el
 *    tipo principal (PISO) en los demás. Los cerrados sin tipo no se tocan.
 * 3. Catálogo del tipo de centro: el PIA en `ciam` y el tipo principal en `css_general`
 *    (en staging ningún tipo de centro tenía tipos de plan, y sin ellos no se puede abrir ninguno).
 *
 * Sin `down()` de datos: la corrección no se deshace.
 */
return new class extends Migration
{
    /**
     * Aplica la corrección.
     *
     * @return void
     */
    public function up(): void
    {
        // 1. tipo derivado del ámbito
        DB::statement("
            UPDATE planes_intervencion p
               SET tipo = CASE tp.ambito WHEN 'asp' THEN 'general_asp' ELSE 'especializado' END
              FROM tipos_plan tp
             WHERE tp.id = p.tipo_plan_id
               AND p.tipo <> CASE tp.ambito WHEN 'asp' THEN 'general_asp' ELSE 'especializado' END
        ");

        $principal = DB::table('tipos_plan')->where('es_principal', true)->value('id');
        $pia = DB::table('tipos_plan')->where('slug', 'pia')->where('ambito', 'especializado')->value('id');

        // 2. planes activos sin tipo; primero los del CIAM, luego el resto al principal
        if ($pia !== null) {
            DB::statement("
                UPDATE planes_intervencion p
                   SET tipo_plan_id = ?, tipo = 'especializado'
                  FROM historias_sociales h
                  JOIN centros c ON c.unidad_organizativa_id = h.unidad_organizativa_id AND c.deleted_at IS NULL
                  JOIN tipos_centro tc ON tc.id = c.tipo_centro_id
                 WHERE h.id = p.historia_id
                   AND tc.codigo = 'ciam'
                   AND p.tipo_plan_id IS NULL
                   AND p.estado = 'activo'
            ", [$pia]);
        }

        if ($principal !== null) {
            DB::table('planes_intervencion')
                ->whereNull('tipo_plan_id')
                ->where('estado', 'activo')
                ->update(['tipo_plan_id' => $principal, 'tipo' => 'general_asp']);
        }

        // 3. catálogo del tipo de centro
        $asociar = function (string $codigoTipoCentro, ?int $tipoPlanId): void {
            $tipoCentro = DB::table('tipos_centro')->where('codigo', $codigoTipoCentro)->value('id');

            if ($tipoCentro !== null && $tipoPlanId !== null) {
                DB::table('tipo_centro_tipo_plan')->insertOrIgnore([
                    'tipo_centro_id' => $tipoCentro,
                    'tipo_plan_id' => $tipoPlanId,
                ]);
            }
        };

        $asociar('ciam', $pia);
        $asociar('css_general', $principal);
    }

    /**
     * Corrección de datos: no se deshace.
     *
     * @return void
     */
    public function down(): void
    {
        //
    }
};
