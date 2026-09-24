<?php

namespace Database\Seeders\Demo\Scenarios;

use App\Models\Ciudadano;
use App\Models\User;
use Database\Seeders\Demo\DemoContextoAditivo;

/**
 * Escenario CIAM: usuaria que solo participa en actividades grupales.
 *
 * Sin historia social ni plan. Se inscribe en el centro y en una o dos
 * actividades del mundo (una prescripción por sesión).
 */
class CiamParticipanteActividad extends EscenarioCiam
{
    /**
     * Construye el escenario de participante en actividades.
     *
     * @param string $clave Clave lógica de la ciudadana
     * @param Ciudadano $ciudadana Ciudadana del mundo
     * @param User $responsable Profesional que la inscribe
     * @param DemoContextoAditivo $ctx Contexto del mundo aditivo
     */
    public function construir(string $clave, Ciudadano $ciudadana, User $responsable, DemoContextoAditivo $ctx): void
    {
        $ctx->inscribirEnActividades($clave, $ciudadana, $this->prescriptora($responsable, $ctx), $ctx->decidir($clave, 'num_actividades', 1, 2));
    }
}
