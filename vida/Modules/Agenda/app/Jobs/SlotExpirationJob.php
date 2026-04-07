<?php

namespace Modules\Agenda\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Actualiza el estado de los slots cuya hora ha pasado al final del día.
 *
 * - Pasa a 'expirado' los slots en estado 'bloqueado_urgencia' cuya hora ha pasado.
 * - Pasa a 'no_ocupado' los slots en estado 'disponible' no reservados cuya hora ha pasado.
 *
 * No realiza ninguna acción sobre profesionales ni citas.
 * Se ejecuta diariamente al final del día laboral vía scheduler.
 */
class SlotExpirationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // TODO: implementar
    }
}
