<?php

namespace App\Console\Commands;

use Database\Seeders\Demo\DemoInvariantChecker;
use Database\Seeders\Demo\DemoMundoAditivoBuilder;
use Database\Seeders\Demo\DemoRegistrador;
use Database\Seeders\Demo\DemoWorldLoader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Comando de carga aditiva de mundos demo.
 *
 * Añade un mundo con `modo: aditivo` a los datos existentes sin borrar nada:
 * nunca ejecuta TRUNCATE, DELETE ni forceDelete. Pensado para entornos con datos
 * de otros equipos (p. ej. staging), donde `demo:reset` no puede usarse.
 *
 * Garantías:
 * - Una única transacción: si algo falla, no queda nada a medias.
 * - Idempotente: lo creado se registra en demo_world_registros con la etiqueta del
 *   mundo, y una segunda carga lo reutiliza (0 registros creados).
 * - --dry-run ejecuta la carga completa y hace rollback, mostrando el resumen.
 * - Se niega a ejecutarse en producción.
 *
 * @see DemoMundoAditivoBuilder
 * @see DemoResetCommand
 */
class DemoLoadCommand extends Command
{
    /** @var string */
    protected $signature = 'demo:load
        {--world= : Nombre del mundo YAML aditivo (sin extensión)}
        {--dry-run : Calcula qué se crearía y qué ya existe, sin persistir nada}';

    /** @var string */
    protected $description = 'Carga un mundo demo aditivo sobre los datos existentes, sin borrar nada (idempotente).';

    /**
     * Excepción interna para forzar el rollback del dry-run tras calcular el resumen.
     */
    private const DRY_RUN = 'dry-run: rollback';

    /**
     * Ejecuta la carga aditiva del mundo.
     *
     * @return int Código de salida (0 = éxito, 1 = error)
     */
    public function handle(): int
    {
        set_time_limit(0);

        if (app()->environment('production')) {
            $this->error('Este comando NO puede ejecutarse en entorno de producción.');

            return self::FAILURE;
        }

        $worldName = $this->option('world');

        if (empty($worldName)) {
            $this->error('Debes especificar el mundo con --world.');

            return self::FAILURE;
        }

        try {
            $config = (new DemoWorldLoader(null, fn (string $msg) => $this->warn($msg)))->load($worldName);
        } catch (\InvalidArgumentException $e) {
            $this->error('Error de validación: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($config['modo'] !== DemoWorldLoader::MODO_ADITIVO) {
            $this->error(
                "El mundo '{$worldName}' es de modo 'reset' y demo:load solo carga mundos aditivos. ".
                "Usa: php artisan demo:reset --world={$worldName}"
            );

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $registrador = new DemoRegistrador($config['etiqueta']);

        $this->newLine();
        $this->line("Mundo: <comment>{$config['meta']['nombre']}</comment> (aditivo, etiqueta <comment>{$config['etiqueta']}</comment>)");
        $this->line('Entorno: '.app()->environment().($dryRun ? ' — <comment>DRY-RUN (no se persistirá nada)</comment>' : ''));
        $this->newLine();

        try {
            DB::transaction(function () use ($config, $registrador, $dryRun) {
                $planIds = (new DemoMundoAditivoBuilder($registrador, fn (string $msg) => $this->line($msg)))
                    ->build($config);

                $this->info('Verificando invariantes de dominio sobre las entidades del mundo...');
                $violaciones = (new DemoInvariantChecker)->check($planIds);

                if ($violaciones !== []) {
                    foreach ($violaciones as $violacion) {
                        $this->error('  '.$violacion);
                    }

                    throw new \RuntimeException('El mundo generado viola invariantes de dominio.');
                }

                $this->info('Invariantes verificadas: sin violaciones.');

                if ($dryRun) {
                    throw new \RuntimeException(self::DRY_RUN);
                }
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== self::DRY_RUN) {
                $this->error('Carga fallida (rollback, no se ha guardado nada): '.$e->getMessage());

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error('Error inesperado (rollback, no se ha guardado nada): '.$e->getMessage());
            $this->line($e->getTraceAsString());

            return self::FAILURE;
        }

        $this->mostrarResumen($registrador, $dryRun);

        return self::SUCCESS;
    }

    /**
     * Muestra el resumen de la carga: creado / ya existente / referenciado.
     *
     * @param DemoRegistrador $registrador Registrador con la contabilidad de la carga
     * @param bool $dryRun Si la carga fue simulada
     */
    private function mostrarResumen(DemoRegistrador $registrador, bool $dryRun): void
    {
        $resumen = $registrador->resumen();
        $tipos = array_unique(array_merge(
            array_keys($resumen['creados']),
            array_keys($resumen['existentes']),
            array_keys($resumen['referenciados'])
        ));
        sort($tipos);

        $this->newLine();
        $this->table(
            ['Entidad', $dryRun ? 'Se crearía' : 'Creado', 'Ya existente', 'Referenciado'],
            array_map(fn (string $tipo) => [
                $tipo,
                $resumen['creados'][$tipo] ?? 0,
                $resumen['existentes'][$tipo] ?? 0,
                $resumen['referenciados'][$tipo] ?? 0,
            ], $tipos)
        );

        $total = $registrador->totalCreados();

        if ($dryRun) {
            $this->info("DRY-RUN: se crearían {$total} registros. Rollback hecho: no se ha guardado nada.");

            return;
        }

        $this->info("Carga completada: {$total} registros creados con la etiqueta {$registrador->etiqueta()}.");
    }
}
