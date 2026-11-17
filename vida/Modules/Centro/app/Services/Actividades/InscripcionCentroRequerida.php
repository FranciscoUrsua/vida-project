<?php

namespace Modules\Centro\Services\Actividades;

use DomainException;
use Modules\Centro\Models\Centro;

/**
 * La actividad exige inscripción en el centro y la persona no la tiene (F6-4).
 *
 * No es un rechazo final: la pantalla lo captura para ofrecer inscribirla en el centro
 * y repite la inscripción con el consentimiento.
 */
class InscripcionCentroRequerida extends DomainException
{
    /**
     * Construye el aviso con el centro que pide la inscripción.
     *
     * @param Centro $centro
     */
    public function __construct(public readonly Centro $centro)
    {
        parent::__construct("La actividad exige que la persona esté inscrita en {$centro->nombre}.");
    }
}
