<?php

namespace Modules\Intervencion\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Documentos\Models\PlantillaInforme;
use Modules\Escalas\Models\TipoEscala;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Models\TipoFicha;

/**
 * Caja de herramientas de un encargo profesional: las fichas de valoración, escalas y
 * plantillas de informe que puede usar su profesional (EA-2 y EA-3 de
 * docs/instrucciones-cli/2026-11-encargo-actuaciones.md).
 *
 * Sale del área del encargo, y solo con lo que está activo. Un encargo sin área (los
 * anteriores al 25 nov 2026) no tiene ninguna de estas tres herramientas; las anotaciones,
 * los documentos y las citas no dependen del área. Es el único sitio que decide qué
 * herramienta ofrece un encargo: los selectores lo usan para pintar y los servicios, para
 * rechazar una herramienta forjada que no es del área.
 */
class CajaHerramientasEncargo
{
    /**
     * Fichas de valoración activas del área del encargo.
     *
     * @param Derivacion $encargo
     *
     * @return Collection<int, TipoFicha>
     */
    public function tiposFicha(Derivacion $encargo): Collection
    {
        if ($encargo->area === null) {
            return new Collection;
        }

        return $encargo->area->tiposFicha()->activos()->deValoracion()->orderBy('nombre')->get();
    }

    /**
     * Escalas activas del área del encargo.
     *
     * @param Derivacion $encargo
     *
     * @return Collection<int, TipoEscala>
     */
    public function tiposEscala(Derivacion $encargo): Collection
    {
        if ($encargo->area === null) {
            return new Collection;
        }

        return $encargo->area->tiposEscala()->where('tipo_escalas.activa', true)->orderBy('nombre')->get();
    }

    /**
     * Plantillas de informe activas del área del encargo.
     *
     * @param Derivacion $encargo
     *
     * @return Collection<int, PlantillaInforme>
     */
    public function plantillasInforme(Derivacion $encargo): Collection
    {
        if ($encargo->area === null) {
            return new Collection;
        }

        return $encargo->area->plantillasInforme()->where('plantillas_informe.activa', true)->orderBy('nombre')->get();
    }

    /**
     * Si el encargo ofrece esa ficha de valoración.
     *
     * @param Derivacion $encargo
     * @param int $tipoFichaId
     *
     * @return bool
     */
    public function ofreceFicha(Derivacion $encargo, int $tipoFichaId): bool
    {
        return $this->tiposFicha($encargo)->contains('id', $tipoFichaId);
    }

    /**
     * Si el encargo ofrece esa escala.
     *
     * @param Derivacion $encargo
     * @param int $tipoEscalaId
     *
     * @return bool
     */
    public function ofreceEscala(Derivacion $encargo, int $tipoEscalaId): bool
    {
        return $this->tiposEscala($encargo)->contains('id', $tipoEscalaId);
    }

    /**
     * Si el encargo ofrece esa plantilla de informe.
     *
     * @param Derivacion $encargo
     * @param int $plantillaId
     *
     * @return bool
     */
    public function ofrecePlantilla(Derivacion $encargo, int $plantillaId): bool
    {
        return $this->plantillasInforme($encargo)->contains('id', $plantillaId);
    }
}
