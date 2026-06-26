<?php

namespace Modules\Agenda\Services;

use DomainException;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Models\CuadranteMes;

/**
 * Publica un CuadranteMes borrador y materializa los slots resultantes.
 *
 * Verifica que no exista ya un cuadrante publicado para el mismo centro y período.
 * La IA nunca puede invocar este servicio directamente; siempre pasa por el supervisor.
 */
class CuadrantePublicadorService
{
    /**
     * Publica el cuadrante y materializa sus slots.
     *
     * @param CuadranteMes $cuadrante Cuadrante en estado 'borrador' o 'revision'
     * @param int $supervisorId ID del usuario que autoriza la publicación
     *
     * @throws DomainException Si ya existe un cuadrante publicado para el mismo período
     */
    public function publicar(CuadranteMes $cuadrante, int $supervisorId): void
    {
        $existePublicado = CuadranteMes::where('centro_id', $cuadrante->centro_id)
            ->where('anyo', $cuadrante->anyo)
            ->where('mes', $cuadrante->mes)
            ->where('estado', EstadoCuadrante::Publicado->value)
            ->where('id', '!=', $cuadrante->id)
            ->exists();

        if ($existePublicado) {
            throw new DomainException(
                'Ya existe un cuadrante publicado para este período. '
                . 'Solo puede haber un cuadrante publicado por centro y mes.'
            );
        }

        $cuadrante->update([
            'estado' => EstadoCuadrante::Publicado->value,
            'publicado_en' => now(),
            'publicado_por_id' => $supervisorId,
        ]);

        (new SlotMaterializadorService)->materializar($cuadrante);
    }
}
