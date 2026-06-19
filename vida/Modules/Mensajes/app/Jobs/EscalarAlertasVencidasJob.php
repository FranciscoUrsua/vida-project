<?php

namespace Modules\Mensajes\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Mensajes\Models\Alerta;
use Modules\Mensajes\Services\AlertaService;

/**
 * Job que detecta alertas con el plazo de reconocimiento vencido
 * y ejecuta la escalada al supervisor de la UO correspondiente.
 *
 * Se ejecuta cada 15 minutos via el scheduler (configurado en
 * MensajesServiceProvider).
 *
 * Requiere que el queue worker esté en ejecución:
 *   php artisan queue:work
 */
class EscalarAlertasVencidasJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Procesa las alertas vencidas y ejecuta su escalado.
     *
     * @param AlertaService $alertaService Servicio de gestión de alertas.
     */
    public function handle(AlertaService $alertaService): void
    {
        $alertasVencidas = Alerta::vencidas()->get();

        if ($alertasVencidas->isEmpty()) {
            return;
        }

        Log::info('EscalarAlertasVencidasJob: procesando alertas vencidas', [
            'total' => $alertasVencidas->count(),
        ]);

        foreach ($alertasVencidas as $alerta) {
            try {
                $alertaService->escalar($alerta);
            } catch (\Throwable $e) {
                Log::error('Error al escalar alerta', [
                    'alerta_id' => $alerta->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
