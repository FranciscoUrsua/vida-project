<?php

namespace Modules\Agenda\Services;

use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Models\CuadranteMes;

/**
 * Publica un CuadranteMes borrador y materializa los slots resultantes.
 *
 * Hay un único cuadrante por centro y mes (índice único en cuadrantes_mes), así
 * que publicar es idempotente: uno ya publicado no se vuelve a materializar.
 * La IA nunca puede invocar este servicio directamente; siempre pasa por el supervisor.
 */
class CuadrantePublicadorService
{
    /**
     * Publica el cuadrante y materializa sus slots.
     *
     * @param CuadranteMes $cuadrante Cuadrante en estado 'borrador' o 'revision'
     * @param int $supervisorId ID del usuario que autoriza la publicación
     * @return void
     */
    public function publicar(CuadranteMes $cuadrante, int $supervisorId): void
    {
        // Solo hay un cuadrante por centro y mes (índice único): publicar el ya publicado no hace nada
        if ($cuadrante->estado === EstadoCuadrante::Publicado) {
            return;
        }

        $cuadrante->update([
            'estado' => EstadoCuadrante::Publicado->value,
            'publicado_en' => now(),
            'publicado_por_id' => $supervisorId,
        ]);

        (new SlotMaterializadorService)->materializar($cuadrante);
    }
}
