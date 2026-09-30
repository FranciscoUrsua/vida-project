<?php

namespace Modules\Intervencion\Services\Asignacion;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Centro\Models\Centro;
use Modules\Intervencion\Enums\EstadoPlan;

/**
 * Actividad de los casos de un centro: asignados, con actividad y dormidos.
 *
 * Un caso tiene actividad si tiene un plan activo o algún apunte en los
 * últimos N meses (configurable por centro). Es información para el
 * supervisor, no una regla: no afecta al sorteo ni cierra historias
 * (docs/modulo-asignacion.md §6). Solo mira fechas y estados, nunca el
 * contenido de los apuntes. Consultas agregadas, sin N+1.
 *
 * Los casos de un centro son las referencias vigentes hechas en él, más las
 * anteriores a la asignación por centro (sin centro) cuya historia está en la
 * UO del centro.
 */
class ActividadCasosService
{
    /**
     * Resumen por profesional de los casos del centro.
     *
     * @param Centro $centro
     * @return Collection<int, array{profesional: User, asignados: int, con_actividad: int, dormidos: int}>
     */
    public function resumen(Centro $centro): Collection
    {
        $casos = $this->casosVigentes($centro)->get(['ap.profesional_id', 'ap.historia_id']);
        $activas = $this->historiasConActividad($centro, $casos->pluck('historia_id')->all());
        $profesionales = User::whereIn('id', $casos->pluck('profesional_id')->unique())->with('profesional')->get()->keyBy('id');

        return $casos->groupBy('profesional_id')
            ->map(function (Collection $suyos, int $profesionalId) use ($activas, $profesionales) {
                $conActividad = $suyos->filter(fn ($c) => isset($activas[$c->historia_id]))->count();

                return [
                    'profesional' => $profesionales[$profesionalId],
                    'asignados' => $suyos->count(),
                    'con_actividad' => $conActividad,
                    'dormidos' => $suyos->count() - $conActividad,
                ];
            })
            ->sortBy(fn (array $fila) => $fila['profesional']->profesional?->apellido1 ?? $fila['profesional']->email)
            ->values();
    }

    /**
     * Si una historia tiene actividad según el criterio del centro.
     *
     * @param HistoriaSocial $historia
     * @param Centro $centro
     * @return bool
     */
    public function conActividad(HistoriaSocial $historia, Centro $centro): bool
    {
        return isset($this->historiasConActividad($centro, [$historia->id])[$historia->id]);
    }

    /**
     * Historias vigentes de un profesional en el centro.
     *
     * @param Centro $centro
     * @param User $profesional
     * @return list<int>
     */
    public function historiasDe(Centro $centro, User $profesional): array
    {
        return $this->casosVigentes($centro)
            ->where('ap.profesional_id', $profesional->id)
            ->pluck('ap.historia_id')
            ->all();
    }

    /**
     * De las historias dadas, las que tienen actividad (como claves del array).
     *
     * @param Centro $centro
     * @param list<int> $historiaIds
     * @return array<int, true>
     */
    public function historiasConActividad(Centro $centro, array $historiaIds): array
    {
        if ($historiaIds === []) {
            return [];
        }

        $conPlan = DB::table('planes_intervencion')
            ->whereIn('historia_id', $historiaIds)
            ->where('estado', EstadoPlan::Activo->value)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('historia_id');

        $conApuntes = DB::table('plan_apuntes')
            ->whereIn('historia_id', $historiaIds)
            ->where('fecha', '>=', now()->subMonths($centro->meses_inactividad_caso))
            ->distinct()
            ->pluck('historia_id');

        return $conPlan->merge($conApuntes)->unique()->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }

    /**
     * Consulta de las referencias vigentes del centro (alias `ap`).
     *
     * @param Centro $centro
     * @return Builder
     */
    private function casosVigentes(Centro $centro): Builder
    {
        return DB::table('asignaciones_profesional as ap')
            ->join('historias_sociales as h', 'h.id', '=', 'ap.historia_id')
            ->whereNull('ap.fecha_fin')
            ->whereNull('ap.deleted_at')
            ->whereNull('h.deleted_at')
            ->where(fn ($q) => $q
                ->where('ap.centro_id', $centro->id)
                ->orWhere(fn ($sin) => $sin
                    ->whereNull('ap.centro_id')
                    ->where('h.unidad_organizativa_id', $centro->unidad_organizativa_id)));
    }
}
