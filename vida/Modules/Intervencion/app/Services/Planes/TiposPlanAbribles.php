<?php

namespace Modules\Intervencion\Services\Planes;

use App\Models\HistoriaSocial;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\TipoPlan;

/**
 * Regla única de qué tipos de plan se pueden abrir para una Historia Social en un centro
 * (fase 3, A1.5; usabilidad U1). La usan la Historia Social, para mostrar u ocultar «+ Abrir plan»,
 * y la pantalla de creación, para ofrecer los tipos. `PlanDeIntervencionService` lo vuelve a
 * comprobar al crear.
 */
class TiposPlanAbribles
{
    /**
     * @param ConfiguracionEfectivaCentro $configuracion
     */
    public function __construct(private readonly ConfiguracionEfectivaCentro $configuracion) {}

    /**
     * Tipos de plan del centro que se pueden abrir para la historia.
     *
     * Parte de los tipos del centro (heredados de su tipo de centro) y quita los que ya tienen
     * un plan vigente en la historia, el principal si el centro no es el de la historia y los
     * especializados sin entrada directa (nacen de una derivación).
     *
     * @param HistoriaSocial $historia
     * @param Centro $centro Centro de la operación (el centro activo del usuario).
     *
     * @return EloquentCollection<int, TipoPlan>
     */
    public function para(HistoriaSocial $historia, Centro $centro): EloquentCollection
    {
        $historia->loadMissing('centro');

        $vigentes = PlanDeIntervencion::withoutGlobalScopes()
            ->where('historia_id', $historia->id)
            ->vigentes()
            ->pluck('tipo_plan_id')
            ->filter()
            ->all();

        return $this->configuracion->tiposPlan($centro)
            ->reject(fn (TipoPlan $tipo): bool => in_array($tipo->id, $vigentes, true)
                || ($tipo->es_principal && $historia->centro?->id !== $centro->id)
                || ($tipo->ambito === 'especializado' && ! $tipo->admite_entrada_directa))
            ->values();
    }
}
