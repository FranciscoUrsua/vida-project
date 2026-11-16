<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Agenda\Services\PublicacionAutomaticaCuadrantes;

/**
 * Publica el cuadrante mensual (y sus huecos de agenda) de los centros que no lo tienen.
 *
 * Uso:
 *   php artisan agenda:publicar-cuadrantes              (mes siguiente, todos los centros)
 *   php artisan agenda:publicar-cuadrantes --mes=2027-01 --centro=13
 *
 * Programado el día 20 de cada mes para el mes siguiente (routes/console.php). No toca
 * los centros que ya tienen cuadrante ese mes ni publica cuadrantes que nacerían vacíos.
 */
class PublicarCuadrantesCommand extends Command
{
    protected $signature = 'agenda:publicar-cuadrantes
                            {--mes= : Mes a publicar (AAAA-MM); por defecto, el siguiente}
                            {--centro=* : Id de los centros; por defecto, todos}';

    protected $description = 'Genera y publica el cuadrante mensual de los centros que no lo tienen';

    /**
     * Ejecuta el comando.
     *
     * @param PublicacionAutomaticaCuadrantes $publicacion
     *
     * @return int
     */
    public function handle(PublicacionAutomaticaCuadrantes $publicacion): int
    {
        $opcion = (string) $this->option('mes');

        if ($opcion !== '' && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $opcion)) {
            $this->error('El mes va en formato AAAA-MM (p. ej. 2027-01).');

            return self::FAILURE;
        }

        $mes = $opcion !== '' ? Carbon::createFromFormat('Y-m-d', $opcion.'-01') : now()->startOfMonth()->addMonth();

        $resultados = $publicacion->publicarMes($mes->year, $mes->month, array_map('intval', (array) $this->option('centro')));

        $this->info('Cuadrantes de '.$mes->format('m/Y').':');
        foreach ($resultados as $r) {
            $this->line("  · {$r['centro']}: {$r['resultado']}".($r['resultado'] === 'publicado' ? " ({$r['slots']} slots)" : ''));
        }

        return self::SUCCESS;
    }
}
