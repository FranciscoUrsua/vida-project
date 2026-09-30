<?php

namespace Modules\Intervencion\Services\Asignacion;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Centro\Enums\TipoAsignacionPendiente;
use Modules\Centro\Models\AsignacionPendiente;
use Modules\Centro\Models\Centro;
use Modules\Intervencion\Enums\EstadoRepartoCasos;
use Modules\Intervencion\Models\RepartoCasos;

/**
 * Lectura de la bandeja de asignaciones de un centro (docs/modulo-asignacion.md §7).
 *
 * Reúne lo que el sistema no ha podido decidir solo y espera al supervisor:
 * personas sin centro, historias sin referencia, propuestas de cambio de centro
 * por domicilio y repartos por salida sin confirmar. Solo lee: las decisiones
 * pasan por AsignacionCentroService, AsignacionReferenciaService y
 * RepartoCasosService. La pantalla y el contador del menú usan este servicio
 * para no calcular el alcance de la bandeja en dos sitios.
 */
class BandejaAsignacionesService
{
    /**
     * Entradas abiertas de un tipo que ve el supervisor del centro, las más antiguas primero.
     *
     * @param Centro $centro
     * @param TipoAsignacionPendiente $tipo
     * @return Collection<int, AsignacionPendiente>
     */
    public function pendientes(Centro $centro, TipoAsignacionPendiente $tipo): Collection
    {
        return $this->consultaPendientes($centro)
            ->where('tipo', $tipo)
            ->with(['ciudadano', 'centro', 'centroPropuesto'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Entrada abierta visible para el supervisor del centro, o null si no existe o no le corresponde.
     *
     * @param Centro $centro
     * @param int $id
     * @return AsignacionPendiente|null
     */
    public function pendiente(Centro $centro, int $id): ?AsignacionPendiente
    {
        return $this->consultaPendientes($centro)->with(['ciudadano', 'historia', 'centroPropuesto'])->find($id);
    }

    /**
     * Repartos por salida del centro pendientes de confirmar.
     *
     * @param Centro $centro
     * @return Collection<int, RepartoCasos>
     */
    public function repartosPropuestos(Centro $centro): Collection
    {
        return RepartoCasos::where('centro_id', $centro->id)
            ->where('estado', EstadoRepartoCasos::Propuesto)
            ->with('profesionalOrigen.profesional')
            ->withCount('lineas')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Número de cosas por decidir en la bandeja del centro (para el menú).
     *
     * @param Centro $centro
     * @return int
     */
    public function total(Centro $centro): int
    {
        return $this->consultaPendientes($centro)->count()
            + RepartoCasos::where('centro_id', $centro->id)->where('estado', EstadoRepartoCasos::Propuesto)->count();
    }

    /**
     * Entradas abiertas del alcance del centro.
     *
     * @param Centro $centro
     * @return Builder<AsignacionPendiente>
     */
    private function consultaPendientes(Centro $centro): Builder
    {
        return AsignacionPendiente::abiertas()->visiblesPara($centro);
    }
}
