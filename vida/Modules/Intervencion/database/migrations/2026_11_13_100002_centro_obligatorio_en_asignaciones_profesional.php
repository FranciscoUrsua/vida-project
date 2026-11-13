<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RN-23 (docs/modulo-tipo-centro.md): la asignación de referencia es de una
 * historia en un centro. Las asignaciones anteriores a la asignación por centro
 * (30 sep 2026) no tienen `centro_id`; se rellena con el centro de la UO de su
 * historia (centro y UO 1:1, addenda D1) y la columna pasa a ser obligatoria.
 *
 * Decisión del desarrollador (13 nov 2026). A esa fecha, en la BD compartida,
 * 474 filas sin centro (371 vigentes), todas con un único centro deducible.
 * Si alguna fila no tiene centro deducible, la migración se detiene sin cambiar nada.
 */
return new class extends Migration
{
    /**
     * Rellena el centro y hace la columna obligatoria.
     *
     * @return void
     *
     * @throws RuntimeException Si queda alguna asignación sin centro deducible.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            // Si una UO tuviera más de un centro (antecesor de baja), se prefiere el activo
            DB::statement(<<<'SQL'
                UPDATE asignaciones_profesional ap
                SET centro_id = (
                    SELECT c.id
                    FROM historias_sociales hs
                    JOIN centros c ON c.unidad_organizativa_id = hs.unidad_organizativa_id
                    WHERE hs.id = ap.historia_id
                    ORDER BY (c.deleted_at IS NULL) DESC, c.id DESC
                    LIMIT 1
                )
                WHERE ap.centro_id IS NULL
            SQL);

            $sinCentro = DB::table('asignaciones_profesional')->whereNull('centro_id')->count();

            if ($sinCentro > 0) {
                throw new RuntimeException("Hay {$sinCentro} asignaciones de referencia sin centro deducible de la UO de su historia: revísalas antes de migrar.");
            }

            DB::statement('ALTER TABLE asignaciones_profesional ALTER COLUMN centro_id SET NOT NULL');
        });
    }

    /**
     * Vuelve a admitir nulos. Los centros rellenados se conservan: son correctos.
     *
     * @return void
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE asignaciones_profesional ALTER COLUMN centro_id DROP NOT NULL');
    }
};
