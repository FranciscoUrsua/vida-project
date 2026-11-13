<?php

namespace Modules\Centro\Console;

use Illuminate\Console\Command;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\TipoCentro;

/**
 * Aplica las denominaciones programadas que entran en vigor: copia la
 * denominación vigente hoy a la caché (nombre y siglas o nombre corto) de cada
 * tipo de centro y centro. Se ejecuta a diario (routes/console.php).
 */
class AplicarDenominacionesCommand extends Command
{
    /** @var string */
    protected $signature = 'denominaciones:aplicar';

    /** @var string */
    protected $description = 'Actualiza el nombre de tipos de centro y centros con la denominación vigente hoy';

    /**
     * Recorre tipos y centros y actualiza los que han cambiado.
     */
    public function handle(): int
    {
        $cambiados = 0;

        foreach ([TipoCentro::query(), Centro::query()] as $consulta) {
            $consulta->whereHas('denominaciones')->each(function ($modelo) use (&$cambiados): void {
                if ($modelo->aplicarDenominacionVigente()) {
                    $cambiados++;
                }
            });
        }

        $this->info("Denominaciones aplicadas: {$cambiados}.");

        return self::SUCCESS;
    }
}
