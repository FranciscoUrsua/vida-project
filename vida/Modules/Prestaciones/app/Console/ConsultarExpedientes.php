<?php

namespace Modules\Prestaciones\Console;

use Illuminate\Console\Command;
use Modules\Prestaciones\Services\Gestor\AdaptadorGestorExpedientes;
use Modules\Prestaciones\Services\SolicitudPrestacionService;

/**
 * Consulta periódica del estado de los expedientes enviados (RN-07.2).
 *
 * Solo hace algo si el adaptador del gestor admite la consulta; con el adaptador manual,
 * nada. Un fallo en una solicitud no para las demás (queda en el log con la causa).
 */
class ConsultarExpedientes extends Command
{
    /** @var string */
    protected $signature = 'expedientes:consultar';

    /** @var string */
    protected $description = 'Consulta en el gestor el estado de los expedientes enviados';

    /**
     * Ejecuta la consulta.
     *
     * @param SolicitudPrestacionService $solicitudes
     *
     * @return int
     */
    public function handle(SolicitudPrestacionService $solicitudes): int
    {
        if (! $solicitudes->gestor()->admite(AdaptadorGestorExpedientes::CONSULTAR)) {
            $this->info("El gestor «{$solicitudes->gestor()->nombre()}» no admite la consulta automática: nada que hacer.");

            return self::SUCCESS;
        }

        $resultado = $solicitudes->consultarPendientes();
        $this->info("Consultadas: {$resultado['consultadas']}. Fallidas: {$resultado['fallidas']}.");

        return self::SUCCESS;
    }
}
