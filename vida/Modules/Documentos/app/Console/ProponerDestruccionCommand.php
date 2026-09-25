<?php

namespace Modules\Documentos\Console;

use Illuminate\Console\Command;
use Modules\Documentos\Services\DestruccionDocumentosService;

/**
 * Genera una propuesta de destrucción con las versiones cuyo plazo de conservación ha vencido.
 *
 * No destruye nada: la propuesta se aprueba o rechaza en Filament (Sistema →
 * Propuestas de eliminación). Se puede programar sin riesgo.
 */
class ProponerDestruccionCommand extends Command
{
    /** @var string */
    protected $signature = 'documentos:proponer-destruccion';

    /** @var string */
    protected $description = 'Propone destruir las versiones de documentos con el plazo de conservación vencido y sin retenciones';

    /**
     * Crea la propuesta e informa de cuántas versiones incluye (solo ids, nunca contenido).
     *
     * @param DestruccionDocumentosService $servicio Servicio de destrucción.
     *
     * @return int
     */
    public function handle(DestruccionDocumentosService $servicio): int
    {
        $propuesta = $servicio->proponer();

        if ($propuesta === null) {
            $this->info('No hay versiones con el plazo de conservación vencido pendientes de proponer.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Propuesta %d creada con %d versiones. Pendiente de aprobación en Filament.',
            $propuesta->id,
            count($propuesta->versiones),
        ));

        return self::SUCCESS;
    }
}
