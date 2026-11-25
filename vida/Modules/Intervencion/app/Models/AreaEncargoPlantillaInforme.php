<?php

namespace Modules\Intervencion\Models;

use DomainException;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Modules\Documentos\Models\PlantillaInforme;
use Modules\Documentos\Support\InformeEncargo;

/**
 * Plantilla de informe ofrecida por un área de encargo.
 *
 * Restricción de dominio (EA-9 de docs/instrucciones-cli/2026-11-encargo-actuaciones.md): una
 * plantilla que lee la Historia Social (fuentes `historia_social.*` o `escalas.*`, merge tags de
 * expediente, valoración o plan) no se puede ofrecer a un profesional de encargo, que solo ve lo
 * suyo. La comprobación está aquí, en el pivote, para que ningún camino (backoffice, seeder,
 * código) la salte; el backoffice la repite antes para dar un mensaje claro.
 *
 * @property int $area_encargo_id
 * @property int $plantilla_informe_id
 */
class AreaEncargoPlantillaInforme extends Pivot
{
    /** @var string */
    protected $table = 'area_encargo_plantilla_informe';

    /** @var bool */
    public $timestamps = false;

    /**
     * Rechaza las plantillas que leen la Historia Social.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::creating(fn (self $pivote) => $pivote->exigirPlantillaAdmisible());
    }

    /**
     * Lanza si la plantilla usa datos de la Historia Social.
     *
     * @return void
     *
     * @throws DomainException
     */
    private function exigirPlantillaAdmisible(): void
    {
        $plantilla = PlantillaInforme::findOrFail($this->plantilla_informe_id);
        $infracciones = InformeEncargo::infracciones($plantilla);

        if ($infracciones !== []) {
            throw new DomainException("La plantilla «{$plantilla->nombre}» no sirve para un encargo: ".implode('; ', $infracciones).'.');
        }
    }
}
