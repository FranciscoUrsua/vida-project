<?php

namespace App\Console\Commands;

use App\Services\Geocodificacion\Callejero\FuenteCallejero;
use App\Services\Geocodificacion\Callejero\ImportacionCallejeroException;
use App\Services\Geocodificacion\Callejero\ImportadorCallejero;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

/**
 * Actualiza la copia local del callejero oficial de Madrid.
 *
 * Sin opciones, descarga la versión vigente del portal de datos abiertos; con
 * `--fichero`, importa un CSV local (tests y emergencias). Si algo falla, la copia
 * anterior se conserva. Planificado semanalmente (routes/console.php).
 *
 * Uso:
 *   php artisan callejero:importar
 *   php artisan callejero:importar --fichero=/ruta/callejero_vigente_numeraciones_202611.csv
 *
 * @see docs/instrucciones-cli/2026-11-callejero-madrid.md
 */
class ImportarCallejeroCommand extends Command
{
    protected $signature = 'callejero:importar
                            {--fichero= : CSV local con el formato del conjunto 200075, en vez de descargarlo}';

    protected $description = 'Importa el callejero oficial de Madrid (números con distrito, barrio, sección y coordenadas)';

    /**
     * Ejecuta la importación.
     *
     * @param FuenteCallejero $fuente Localiza y descarga el fichero vigente.
     * @param ImportadorCallejero $importador Carga el fichero en la tabla.
     *
     * @return int
     */
    public function handle(FuenteCallejero $fuente, ImportadorCallejero $importador): int
    {
        $local = $this->option('fichero');
        $temporal = null;

        try {
            if ($local !== null) {
                if (! is_file($local)) {
                    $this->error("No existe el fichero {$local}.");

                    return self::FAILURE;
                }

                $ruta = $local;
                $version = FuenteCallejero::versionDe($local);
                $origen = (string) realpath($local);
            } else {
                ['url' => $origen, 'version' => $version] = $fuente->recursoVigente();
                $this->line("Descargando la versión {$version}…");
                $ruta = $temporal = storage_path('app/callejero/callejero-'.getmypid().'.csv');
                $fuente->descargar($origen, $ruta);
            }

            $importacion = $importador->importar($ruta, $version, $origen);
        } catch (ImportacionCallejeroException|ConnectionException $e) {
            Log::warning('callejero:importar: '.$e->getMessage());
            $this->error($e->getMessage().' La copia anterior del callejero se conserva.');

            return self::FAILURE;
        } finally {
            if ($temporal !== null && is_file($temporal)) {
                unlink($temporal);
            }
        }

        $this->info("Callejero importado: {$importacion->numeros} números (versión {$importacion->version}, {$importacion->descartados} filas descartadas).");

        return self::SUCCESS;
    }
}
