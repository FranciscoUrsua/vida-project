<?php

namespace Database\Seeders\Demo\Scenarios;

use App\Models\Ciudadano;
use App\Models\User;
use Database\Seeders\Demo\DemoContextoAditivo;
use Modules\Intervencion\Enums\MotivoCierre;
use Modules\Intervencion\Enums\TipoEntrevista;

/**
 * Escenario CIAM: usuaria con plan PIA de entrada directa ya cerrado.
 *
 * Historia social cerrada, plan del tipo del mundo (PIA) cerrado sin plan ASP y
 * entre 3 y 6 seguimientos anteriores al cierre. El motivo de cierre es mayoritariamente
 * consecución de objetivos (≈75 %) y, en el resto, finalización de la intervención.
 * Aproximadamente un tercio participó en alguna actividad del centro.
 */
class CiamPiaCerrada extends EscenarioCiam
{
    /**
     * Construye el escenario de plan PIA cerrado.
     *
     * @param string $clave Clave lógica de la ciudadana
     * @param Ciudadano $ciudadana Ciudadana del mundo
     * @param User $responsable Profesional responsable del plan
     * @param DemoContextoAditivo $ctx Contexto del mundo aditivo
     */
    public function construir(string $clave, Ciudadano $ciudadana, User $responsable, DemoContextoAditivo $ctx): void
    {
        $historia = $this->crearHistoria($clave, $ciudadana, 'cerrada', $ctx);

        $fechaInicial = today()->subDays(mt_rand(400, 700))->setTime(mt_rand(9, 13), 0);
        $inicioPlan = $fechaInicial->copy()->addDays(mt_rand(7, 20))->startOfDay();
        $cierre = $inicioPlan->copy()->addDays(mt_rand(180, 330))->min(today()->subDays(15));

        $motivo = $ctx->decidir($clave, 'motivo_cierre', 1, 4) === 1 ? MotivoCierre::FinIntervencion : MotivoCierre::ConsecucionObjetivos;

        $this->crearEntrevista("{$clave}.entrevista_inicial", $historia, $responsable, $fechaInicial, TipoEntrevista::Inicial, null, $ctx);
        $this->crearAsignacion($clave, $historia, $responsable, $fechaInicial->copy()->startOfDay(), $cierre, $ctx);

        $plan = $this->crearPlan($clave, $historia, $responsable, $inicioPlan, $cierre, $motivo, $ctx);

        $this->crearSeguimientos($clave, $historia, $plan, $responsable, $ctx->decidir($clave, 'seguimientos', 3, 6), $inicioPlan, $cierre, $ctx);

        if ($ctx->decidir($clave, 'participa', 1, 3) === 1) {
            $ctx->inscribirEnActividades($clave, $ciudadana, $this->prescriptora($responsable, $ctx), 1);
        }
    }
}
