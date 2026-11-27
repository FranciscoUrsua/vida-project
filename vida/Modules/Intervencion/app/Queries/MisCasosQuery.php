<?php

namespace Modules\Intervencion\Queries;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Intervencion\Enums\EstadoPlan;

/**
 * Historias Sociales que lleva un profesional: las que tiene como referencia
 * (asignación vigente) y las que tienen un plan en curso del que es responsable
 * (p. ej. el PIA en el CIAM), cada una una sola vez.
 *
 * Es la definición de «Mis casos»: la usan el listado y el contador del menú,
 * para que el número del menú coincida siempre con las filas del listado.
 */
class MisCasosQuery
{
    /**
     * Asignaciones vigentes de referencia del profesional.
     *
     * @param int $usuarioId
     *
     * @return Builder Columnas `historia_id` y `fecha_inicio`.
     */
    public static function referencias(int $usuarioId): Builder
    {
        return DB::table('asignaciones_profesional')
            ->select('historia_id', 'fecha_inicio')
            ->where('profesional_id', $usuarioId)
            ->whereNull('fecha_fin')
            ->whereNull('deleted_at');
    }

    /**
     * Una fila por historia del profesional, con la fecha más antigua de las dos vías
     * (inicio de la referencia o del plan), sin las historias dadas de baja.
     *
     * @param int $usuarioId
     *
     * @return Builder Columnas `historia_id`, `fecha_inicio` y `ciudadano_id`, sobre el alias `mh`.
     */
    public static function historias(int $usuarioId): Builder
    {
        $responsabilidades = DB::table('planes_intervencion')
            ->select('historia_id', 'fecha_inicio')
            ->where('profesional_responsable_id', $usuarioId)
            ->where('estado', '!=', EstadoPlan::Cerrado->value)
            ->whereNull('deleted_at');

        $porHistoria = DB::query()
            ->fromSub(self::referencias($usuarioId)->unionAll($responsabilidades), 'u')
            ->selectRaw('historia_id, MIN(fecha_inicio) AS fecha_inicio')
            ->groupBy('historia_id');

        return DB::query()
            ->fromSub($porHistoria, 'mh')
            ->join('historias_sociales as hs', 'hs.id', '=', 'mh.historia_id')
            ->whereNull('hs.deleted_at')
            ->select(['mh.historia_id', 'mh.fecha_inicio', 'hs.ciudadano_id']);
    }
}
