<?php

namespace App\Console\Commands;

use Database\Seeders\Demo\DemoWorldLoader;
use Illuminate\Console\Command;

/**
 * Comando de validación de mundos YAML para entornos de demo.
 *
 * Valida la estructura y semántica del fichero YAML sin tocar la base de datos.
 * Útil para verificar mundos antes de aplicarlos, o en CI/CD.
 *
 * Devuelve código 0 si el YAML es válido, código 1 si hay errores de validación.
 */
class DemoValidateCommand extends Command
{
    /** @var string */
    protected $signature = 'demo:validate {world? : Nombre del mundo YAML a validar (sin extensión)}';

    /** @var string */
    protected $description = 'Valida un fichero YAML de mundo demo sin modificar la base de datos.';

    /**
     * Ejecuta la validación del mundo YAML.
     *
     * @return int Código de salida (0 = válido, 1 = inválido)
     */
    public function handle(): int
    {
        $loader = new DemoWorldLoader(null, fn (string $msg) => $this->warn($msg));

        $worldName = $this->argument('world');

        if (empty($worldName)) {
            $worlds = $loader->listWorlds();

            if (empty($worlds)) {
                $this->error('No hay mundos YAML disponibles en database/seeders/worlds/.');

                return self::FAILURE;
            }

            $worldName = $this->choice('Selecciona el mundo a validar:', $worlds, 0);
        }

        $this->info("Validando mundo: {$worldName}...");

        try {
            $worldConfig = $loader->load($worldName);
        } catch (\InvalidArgumentException $e) {
            $this->error('Validación fallida: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("Mundo '{$worldConfig['meta']['nombre']}' es válido.");
        $this->line("  Descripción: {$worldConfig['meta']['descripcion']}");
        $this->line('  Centros: ' . count($worldConfig['centros']));
        $this->line('  Profesionales: ' . count($worldConfig['profesionales']));

        $totalCiudadanos = 0;

        foreach ($worldConfig['escenarios'] as $esc) {
            foreach ($esc['ciudadanos'] as $c) {
                $totalCiudadanos += $c['cantidad'];
            }
        }

        $this->line("  Ciudadanos a generar: ~{$totalCiudadanos}");

        return self::SUCCESS;
    }
}
