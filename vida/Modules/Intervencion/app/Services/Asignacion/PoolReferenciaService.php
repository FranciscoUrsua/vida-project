<?php

namespace Modules\Intervencion\Services\Asignacion;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Agenda\Models\ExcepcionProfesional;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Centro\Models\Centro;

/**
 * Profesionales que entran en el reparto de referencias de un centro en una fecha.
 *
 * Entran quienes tienen perfil horario activo y vigente en el centro y un cargo
 * marcado como elegible para referencia. Su peso es su jornada semanal en el
 * centro. Quedan fuera mientras dure una ausencia registrada más larga que el
 * umbral de ausencia prolongada del centro: sus casos no cambian, pero no
 * reciben entradas nuevas (RN-07).
 *
 * @see docs/modulo-asignacion.md §4.1
 */
class PoolReferenciaService
{
    /** Umbral por defecto si el centro no tiene horario vigente. */
    private const DIAS_AUSENCIA_POR_DEFECTO = 15;

    /**
     * Profesionales elegibles con su peso, ordenados por id de usuario.
     *
     * @param Centro $centro
     * @param Carbon $fecha
     * @return Collection<int, array{usuario: User, peso: float}>
     */
    public function elegibles(Centro $centro, Carbon $fecha): Collection
    {
        $dia = $fecha->toDateString();
        $perfiles = $this->perfilesReferencia($centro, $dia);

        $ausentes = $this->ausentesProlongados($centro, $dia, $perfiles->pluck('usuario_id')->all());

        return $perfiles
            ->reject(fn (PerfilHorarioProfesional $p) => in_array($p->usuario_id, $ausentes, true))
            ->map(fn (PerfilHorarioProfesional $p) => ['usuario' => $p->usuario, 'peso' => (float) $p->jornada_semanal_horas])
            ->sortBy(fn (array $e) => $e['usuario']->id)
            ->values();
    }

    /**
     * Profesionales del centro que pueden ser referencia, estén o no ausentes:
     * entre ellos elige el supervisor en una asignación manual, también cuando
     * el sorteo no tiene a nadie por las ausencias.
     *
     * @param Centro $centro
     * @param Carbon $fecha
     * @return Collection<int, User>
     */
    public function profesionalesReferencia(Centro $centro, Carbon $fecha): Collection
    {
        return $this->perfilesReferencia($centro, $fecha->toDateString())
            ->map(fn (PerfilHorarioProfesional $p) => $p->usuario)
            ->unique('id')
            ->sortBy('id')
            ->values();
    }

    /**
     * Perfiles horarios activos y vigentes en el centro de usuarios con cargo
     * elegible para referencia.
     *
     * @param Centro $centro
     * @param string $dia Fecha Y-m-d.
     * @return Collection<int, PerfilHorarioProfesional>
     */
    private function perfilesReferencia(Centro $centro, string $dia): Collection
    {
        return PerfilHorarioProfesional::query()
            ->where('centro_id', $centro->id)
            ->where('activo', true)
            ->whereDate('vigente_desde', '<=', $dia)
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $dia))
            ->whereHas('usuario.profesional.cargo', fn ($q) => $q->where('puede_ser_referencia', true))
            ->with('usuario.profesional')
            ->get()
            ->toBase();
    }

    /**
     * Umbral de ausencia prolongada del centro en una fecha (días).
     *
     * @param Centro $centro
     * @param string $dia Fecha Y-m-d.
     * @return int
     */
    public function diasAusenciaProlongada(Centro $centro, string $dia): int
    {
        return HorarioCentro::query()
            ->where('centro_id', $centro->id)
            ->where('activo', true)
            ->whereDate('vigente_desde', '<=', $dia)
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $dia))
            ->orderByDesc('vigente_desde')
            ->value('dias_ausencia_prolongada') ?? self::DIAS_AUSENCIA_POR_DEFECTO;
    }

    /**
     * Usuarios con una ausencia que afecta a su disponibilidad en el centro,
     * cubre el día y dura más que el umbral.
     *
     * @param Centro $centro
     * @param string $dia Fecha Y-m-d.
     * @param list<int> $usuarios
     * @return list<int>
     */
    private function ausentesProlongados(Centro $centro, string $dia, array $usuarios): array
    {
        $umbral = $this->diasAusenciaProlongada($centro, $dia);

        return ExcepcionProfesional::query()
            ->where('centro_id', $centro->id)
            ->whereIn('usuario_id', $usuarios)
            ->where('afecta_disponibilidad', true)
            ->whereDate('fecha_inicio', '<=', $dia)
            ->whereDate('fecha_fin', '>=', $dia)
            ->get(['usuario_id', 'fecha_inicio', 'fecha_fin'])
            // Duración en días naturales, contando el primero y el último
            ->filter(fn (ExcepcionProfesional $e) => $e->fecha_inicio->diffInDays($e->fecha_fin) + 1 > $umbral)
            ->pluck('usuario_id')
            ->unique()
            ->values()
            ->all();
    }
}
