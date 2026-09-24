<?php

namespace Database\Seeders\Demo\Scenarios;

use App\Models\Ciudadano;
use App\Models\User;
use Database\Seeders\Demo\DemoContextoAditivo;
use Modules\Intervencion\Enums\TipoEntrevista;

/**
 * Escenario CIAM: usuaria con plan PIA activo de entrada directa.
 *
 * Historia social abierta en la UO del CIAM, entrevista inicial, plan del tipo
 * del mundo (PIA) especializado y activo sin plan ASP (el CIAM es puerta de
 * entrada alternativa) y entre 2 y 4 seguimientos. Aproximadamente un tercio
 * participa además en alguna actividad del centro.
 */
class CiamPiaActiva extends EscenarioCiam
{
    /**
     * Construye el escenario de plan PIA activo.
     *
     * @param string $clave Clave lógica de la ciudadana
     * @param Ciudadano $ciudadana Ciudadana del mundo
     * @param User $responsable Profesional responsable del plan
     * @param DemoContextoAditivo $ctx Contexto del mundo aditivo
     */
    public function construir(string $clave, Ciudadano $ciudadana, User $responsable, DemoContextoAditivo $ctx): void
    {
        $historia = $this->crearHistoria($clave, $ciudadana, 'abierta', $ctx);

        $fechaInicial = today()->subDays(mt_rand(100, 300))->setTime(mt_rand(9, 13), 0);
        $inicioPlan = $fechaInicial->copy()->addDays(mt_rand(7, 20))->startOfDay();

        $this->crearEntrevista("{$clave}.entrevista_inicial", $historia, $responsable, $fechaInicial, TipoEntrevista::Inicial, null, $ctx);
        $this->crearAsignacion($clave, $historia, $responsable, $fechaInicial->copy()->startOfDay(), null, $ctx);

        $plan = $this->crearPlan($clave, $historia, $responsable, $inicioPlan, null, null, $ctx);

        $this->crearSeguimientos($clave, $historia, $plan, $responsable, $ctx->decidir($clave, 'seguimientos', 2, 4), $inicioPlan, today()->subDay(), $ctx);

        if ($ctx->decidir($clave, 'participa', 1, 3) === 1) {
            $ctx->inscribirEnActividades($clave, $ciudadana, $this->prescriptora($responsable, $ctx), 1);
        }
    }
}
