<?php

namespace App\Console\Commands;

use App\Services\Catalogos\CargaCatalogosOficiales;
use App\Services\Catalogos\Normalizacion\NormalizadorCatalogos;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Regenera los CSV normalizados de los catálogos comunes a partir de los ficheros
 * oficiales de `database/data/catalogos/fuentes/` (bloque A de la ficha sociodemográfica).
 *
 * Es reproducible: con los mismos originales produce los mismos CSV. Se ejecuta a mano
 * cuando se descarga una versión nueva de una fuente, y se versionan juntos el original
 * y los CSV. **No** se ejecuta al desplegar: al desplegar se cargan los CSV ya generados.
 *
 * Uso:
 *   php artisan catalogos:normalizar
 *
 * @see database/data/catalogos/fuentes/README.md
 */
class NormalizarCatalogosCommand extends Command
{
    protected $signature = 'catalogos:normalizar
                            {--fuentes= : Carpeta de los originales (por defecto database/data/catalogos/fuentes)}
                            {--destino= : Carpeta de los CSV (por defecto database/data/catalogos)}';

    protected $description = 'Genera los CSV de los catálogos comunes (países, estudios, situación laboral, idiomas) desde las fuentes oficiales';

    /**
     * Normaliza los cuatro catálogos y escribe sus CSV.
     *
     * @return int
     */
    public function handle(): int
    {
        $destino = rtrim($this->option('destino') ?? database_path('data/catalogos'), '/');

        try {
            $normalizador = new NormalizadorCatalogos($this->option('fuentes') ?? $destino.'/fuentes');
            $catalogos = [
                'comun.pais' => $normalizador->paises(),
                'comun.nivel_estudios' => $normalizador->nivelesEstudios(),
                'comun.situacion_laboral' => $normalizador->situacionesLaborales(),
                'comun.idioma' => $normalizador->idiomas(),
            ];
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($catalogos as $grupo => $filas) {
            $normalizador->escribir($destino.'/'.CargaCatalogosOficiales::FICHEROS[$grupo], $filas);
            $this->info(sprintf('✓ %s: %d valores', $grupo, count($filas)));
        }

        return self::SUCCESS;
    }
}
