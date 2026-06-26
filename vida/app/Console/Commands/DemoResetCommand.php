<?php

namespace App\Console\Commands;

use Database\Seeders\Demo\DemoActividadBuilder;
use Database\Seeders\Demo\DemoInvariantChecker;
use Database\Seeders\Demo\DemoScenarioBuilder;
use Database\Seeders\Demo\DemoWorldBuilder;
use Database\Seeders\Demo\DemoWorldLoader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Comando de reset de entorno de demo.
 *
 * Destruye todos los datos de ciudadanos, historias, planes, entrevistas
 * y seguimientos, y reconstruye el mundo desde el fichero YAML indicado.
 *
 * NUNCA ejecutar en producción — el comando lo verifica y aborta.
 *
 * Flujo:
 * 1. Verificar entorno (no producción)
 * 2. Confirmar si staging
 * 3. Cargar y validar YAML
 * 4. Mostrar resumen
 * 5. TRUNCATE tablas en cascada
 * 6. Construir mundo (UOs + profesionales)
 * 7. Construir escenarios (ciudadanos + trayectorias)
 * 8. Verificar invariantes
 * 9. Commit o rollback
 */
class DemoResetCommand extends Command
{
    /** @var string */
    protected $signature = 'demo:reset {--world= : Nombre del mundo YAML (sin extensión)}';

    /** @var string */
    protected $description = 'Destruye y reconstruye el entorno de demo desde un fichero YAML de mundo.';

    /**
     * Tablas a truncar en orden (CASCADE gestiona FK en PostgreSQL).
     *
     * @var list<string>
     */
    private const TABLAS_TRUNCAR = [
        'plan_apuntes',
        'seguimientos_plan',
        'firmas_plan',
        'revisiones_plan',
        'planes_intervencion',
        'valoraciones',
        'fichas',
        'entrevistas',
        'historias_sociales',
        'sia_contactos',
        'ciudadanos',   // CASCADE vacía inscripciones_centro (FK RESTRICT desde centros)
        'centros',      // CASCADE vacía ambitos_territoriales, colecciones_plazas, horarios, etc.
    ];

    /**
     * Ejecuta el comando de reset del mundo demo.
     *
     * @return int Código de salida (0 = éxito, 1 = error)
     */
    public function handle(): int
    {
        // El reset puede crear cientos de registros con casts encrypted y bcrypt;
        // sin este límite PHP-FPM lo mata antes de terminar cuando se invoca desde web.
        set_time_limit(0);

        // 1. Verificar entorno
        if (app()->isProduction()) {
            $this->error('Este comando NO puede ejecutarse en entorno de producción.');

            return self::FAILURE;
        }

        // 2. Confirmar si staging (solo en ejecución interactiva — en web ya hay modal de confirmación)
        if (app()->environment('staging') && $this->input->isInteractive()) {
            if (! $this->confirm('⚠️  Estás en entorno STAGING. ¿Continuar con el reset?', false)) {
                $this->info('Operación cancelada.');

                return self::SUCCESS;
            }
        }

        // 3. Determinar mundo a cargar
        $worldName = $this->option('world');

        $loader = new DemoWorldLoader(null, fn (string $msg) => $this->warn($msg));

        if (empty($worldName)) {
            if (! $this->input->isInteractive()) {
                $this->error('Debes especificar el mundo con --world cuando se ejecuta de forma no interactiva.');

                return self::FAILURE;
            }

            $worlds = $loader->listWorlds();

            if (empty($worlds)) {
                $this->error('No hay mundos YAML disponibles en database/seeders/worlds/.');

                return self::FAILURE;
            }

            $worldName = $this->choice('Selecciona el mundo a cargar:', $worlds, 0);
        }

        // 4. Cargar y validar YAML
        try {
            $worldConfig = $loader->load($worldName);
        } catch (\InvalidArgumentException $e) {
            $this->error('Error de validación: '.$e->getMessage());

            return self::FAILURE;
        }

        // 5. Mostrar resumen
        $this->mostrarResumen($worldConfig);

        // 6. Ejecutar reset en transacción
        try {
            DB::transaction(function () use ($worldConfig) {
                // TRUNCATE de tablas en orden con CASCADE
                $this->truncatarTablas();

                // Construir mundo (UOs + profesionales)
                $this->info('Construyendo infraestructura...');
                $builder = new DemoWorldBuilder(fn (string $msg) => $this->line($msg));
                $resultado = $builder->build($worldConfig);

                // Construir escenarios
                $this->info('Construyendo escenarios de ciudadanos...');
                $scenarioBuilder = new DemoScenarioBuilder(fn (string $msg) => $this->line($msg));
                $scenarioBuilder->buildScenarios(
                    $worldConfig['escenarios'],
                    $resultado['profesionales'],
                    $resultado['unidades']
                );

                // Construir actividades grupales y sesiones
                if (! empty($worldConfig['actividades'])) {
                    $this->info('Construyendo actividades grupales...');
                    $actividadBuilder = new DemoActividadBuilder(fn (string $msg) => $this->line($msg));
                    $actividadBuilder->buildActividades(
                        $worldConfig['actividades'],
                        $resultado['centros'],
                        $resultado['salas'],
                        $resultado['profesionales']
                    );
                }

                // Verificar invariantes
                $this->info('Verificando invariantes de dominio...');
                $checker = new DemoInvariantChecker;
                $violaciones = $checker->check();

                if (! empty($violaciones)) {
                    $this->error('Violaciones de invariantes detectadas:');

                    foreach ($violaciones as $violacion) {
                        $this->error('  '.$violacion);
                    }

                    throw new \RuntimeException('El mundo generado viola invariantes de dominio.');
                }

                $this->info('Invariantes verificadas: sin violaciones.');
            });
        } catch (\RuntimeException $e) {
            $this->error('Reset fallido (rollback): '.$e->getMessage());

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Error inesperado (rollback): '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Mundo '{$worldConfig['meta']['nombre']}' cargado correctamente.");

        return self::SUCCESS;
    }

    /**
     * Muestra el resumen del mundo que se va a cargar.
     *
     * @param array{meta: array{nombre: string, descripcion: string, reset_cada: string}, centros: list<mixed>, profesionales: list<mixed>, escenarios: list<mixed>, actividades: list<mixed>} $worldConfig
     */
    private function mostrarResumen(array $worldConfig): void
    {
        $this->newLine();
        $this->line("Mundo: <comment>{$worldConfig['meta']['nombre']}</comment>");
        $this->line("Descripción: {$worldConfig['meta']['descripcion']}");
        $this->line('Centros: '.count($worldConfig['centros']));
        $this->line('Profesionales: '.count($worldConfig['profesionales']));

        $totalCiudadanos = 0;

        foreach ($worldConfig['escenarios'] as $esc) {
            foreach ($esc['ciudadanos'] as $c) {
                $totalCiudadanos += $c['cantidad'];
            }
        }

        $this->line("Ciudadanos a crear: ~{$totalCiudadanos}");

        $totalActividades = count($worldConfig['actividades']);

        if ($totalActividades > 0) {
            $totalSesiones = array_sum(array_map(
                fn ($a) => count($a['sesiones'] ?? []),
                $worldConfig['actividades']
            ));
            $this->line("Actividades grupales: {$totalActividades} ({$totalSesiones} sesiones)");
        }

        $this->newLine();
    }

    /**
     * Trunca las tablas de datos de demo en orden, ignorando las que no existen.
     *
     * En PostgreSQL, TRUNCATE ... CASCADE elimina también las filas dependientes,
     * por lo que el orden es orientativo pero el CASCADE garantiza consistencia.
     */
    private function truncatarTablas(): void
    {
        $this->info('Truncando tablas existentes...');

        // Obtener tablas que realmente existen en la BD
        $tablasExistentes = DB::select(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public'"
        );
        $nombresExistentes = array_column($tablasExistentes, 'tablename');

        foreach (self::TABLAS_TRUNCAR as $tabla) {
            if (! in_array($tabla, $nombresExistentes, true)) {
                $this->warn("  Tabla '{$tabla}' no existe — omitida.");

                continue;
            }

            DB::statement("TRUNCATE TABLE {$tabla} CASCADE");
            $this->line("  TRUNCATE {$tabla}");
        }
    }
}
