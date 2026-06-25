<?php

namespace Modules\Supervision\Services;

use App\Models\HistoriaSocial;
use App\Models\User;
use App\Models\UsuarioUo;
use Modules\Intervencion\Models\AsignacionProfesional;

/**
 * Servicio que computa los KPIs del dashboard de supervisión.
 *
 * Proporciona indicadores operativos del día a día del centro:
 * ratio de carga, espera media para primera cita,
 * profesionales sin agenda activa y actividades próximas.
 * No es analítica histórica; todos los valores son del momento presente.
 */
class IndicadoresCentroService
{
    /**
     * Ratio personas/profesional para la UO indicada.
     *
     * Historias sociales no cerradas con asignación vigente a un profesional de la UO,
     * divididas entre el número de profesionales distintos con asignación vigente en esa UO.
     * Devuelve 0.0 si no hay profesionales activos para evitar división por cero.
     *
     * @param int $uoId ID de la Unidad Organizativa
     * @return float
     */
    public function ratioCarga(int $uoId): float
    {
        // Profesionales con asignación vigente en la UO
        $profesionalesEnUo = UsuarioUo::where('unidad_organizativa_id', $uoId)
            ->vigentes()
            ->pluck('usuario_id');

        // Solo los profesionales de la UO que tienen asignaciones vigentes
        $asignacionesVigentes = AsignacionProfesional::whereNull('fecha_fin')
            ->whereNull('deleted_at')
            ->whereIn('profesional_id', $profesionalesEnUo);

        $totalProfesionales = (clone $asignacionesVigentes)
            ->distinct('profesional_id')
            ->count('profesional_id');

        if ($totalProfesionales === 0) {
            return 0.0;
        }

        // Historias activas (no cerradas) con asignación vigente a alguno de esos profesionales
        $historiasActivas = (clone $asignacionesVigentes)
            ->whereHas('historia', fn ($q) => $q->where('estado', '!=', 'cerrada'))
            ->distinct('historia_id')
            ->count('historia_id');

        return round($historiasActivas / $totalProfesionales, 1);
    }

    /**
     * Número de profesionales de la UO con asignación vigente.
     *
     * @param int $uoId ID de la Unidad Organizativa
     * @return int
     */
    public function profesionalesActivos(int $uoId): int
    {
        return UsuarioUo::where('unidad_organizativa_id', $uoId)
            ->vigentes()
            ->distinct('usuario_id')
            ->count('usuario_id');
    }
}
