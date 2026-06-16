<?php

namespace App\Console\Commands;

use App\Models\Ciudadano;
use App\Services\Geocodificacion\GeocodificadorInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Modules\Centro\Models\Centro;

/**
 * Comando artisan para normalización masiva de direcciones pendientes.
 *
 * Procesa en batches de 100 registros con throttling de 1 segundo entre
 * batches para no saturar el proveedor de geocoding ni el sistema.
 *
 * Uso:
 *   php artisan vida:normalizar-direcciones --entidad=ciudadano --pendientes
 *   php artisan vida:normalizar-direcciones --entidad=centro
 *
 * Ver docs/geocodificacion.md § 4.3.
 */
class NormalizarDirecciones extends Command
{
    protected $signature = 'vida:normalizar-direcciones
                            {--entidad= : Entidad a procesar: ciudadano, centro}
                            {--pendientes : Procesa solo registros con direccion_normalizada = false}';

    protected $description = 'Normaliza las direcciones de ciudadanos y/o centros mediante el geocoder activo';

    /** Tamaño de los batches de procesamiento. */
    private const BATCH_SIZE = 100;

    /**
     * Mapa de identificadores de entidad a clases de modelo.
     *
     * @var array<string, class-string>
     */
    private const ENTIDADES = [
        'ciudadano' => Ciudadano::class,
        'centro' => Centro::class,
    ];

    /**
     * Ejecuta el comando.
     *
     * @param GeocodificadorInterface $geocodificador Servicio usado para normalizar registros.
     *
     * @return int Codigo de salida del comando.
     */
    public function handle(GeocodificadorInterface $geocodificador): int
    {
        $entidadClave = $this->option('entidad');
        $soloPendientes = (bool) $this->option('pendientes');

        $entidades = $this->resolverEntidades($entidadClave);

        if (empty($entidades)) {
            $this->error('Entidad no reconocida. Valores válidos: '.implode(', ', array_keys(self::ENTIDADES)));

            return self::FAILURE;
        }

        foreach ($entidades as $clave => $claseModelo) {
            $this->procesarEntidad($clave, $claseModelo, $geocodificador, $soloPendientes);
        }

        return self::SUCCESS;
    }

    /**
     * Procesa todos los registros de una entidad.
     *
     * @param class-string $claseModelo
     */
    private function procesarEntidad(
        string $clave,
        string $claseModelo,
        GeocodificadorInterface $geocodificador,
        bool $soloPendientes,
    ): void {
        $this->info("Procesando entidad: {$clave}");

        $consulta = $claseModelo::query();

        if ($soloPendientes) {
            $consulta->sinNormalizar();
        }

        $total = $consulta->count();
        $procesados = 0;
        $exitosos = 0;
        $fallidos = 0;

        if ($total === 0) {
            $this->line('  Sin registros pendientes.');

            return;
        }

        $this->line("  Registros a procesar: {$total}");
        $barra = $this->output->createProgressBar($total);
        $barra->start();

        $consulta->chunkById(self::BATCH_SIZE, function ($registros) use (
            $geocodificador, &$procesados, &$exitosos, &$fallidos, $barra
        ) {
            foreach ($registros as $registro) {
                if (empty($registro->direccion_texto)) {
                    $procesados++;
                    $barra->advance();

                    continue;
                }

                try {
                    $resultado = $geocodificador->normalizar((string) $registro->direccion_texto);

                    if ($resultado->exito) {
                        $registro->withoutEvents(function () use ($registro, $resultado) {
                            $registro->update([
                                'direccion_normalizada' => true,
                                'tipo_via' => $resultado->tipoVia,
                                'nombre_via' => $resultado->nombreVia,
                                'tipo_numeracion' => $resultado->tipoNumeracion,
                                'numero' => $resultado->numero,
                                'portal' => $resultado->portal,
                                'escalera' => $resultado->escalera,
                                'piso' => $resultado->piso,
                                'puerta' => $resultado->puerta,
                                'codigo_postal' => $resultado->codigoPostal ?? $registro->codigo_postal,
                                'municipio' => $resultado->municipio,
                                'coordenadas_lat' => $resultado->latitud,
                                'coordenadas_lng' => $resultado->longitud,
                                'geocoder_proveedor' => $resultado->proveedor,
                            ]);
                        });
                        $exitosos++;
                    } else {
                        $fallidos++;
                    }
                } catch (\Throwable) {
                    $fallidos++;
                }

                $procesados++;
                $barra->advance();
            }

            // Throttling entre batches: evita saturar el proveedor de geocoding
            if ($procesados < $this->getTotalRegistros($registros)) {
                sleep(1);
            }
        });

        $barra->finish();
        $this->newLine();
        $this->line("  Exitosos: {$exitosos} | Fallidos: {$fallidos} | Total: {$procesados}");
    }

    /**
     * Resuelve las clases de modelo a procesar según el argumento --entidad.
     *
     * Si no se especifica entidad, procesa todas.
     *
     * @return array<string, class-string>
     */
    private function resolverEntidades(?string $clave): array
    {
        if ($clave === null) {
            return self::ENTIDADES;
        }

        $claveLower = strtolower($clave);

        if (! isset(self::ENTIDADES[$claveLower])) {
            return [];
        }

        return [$claveLower => self::ENTIDADES[$claveLower]];
    }

    /**
     * Devuelve el total de registros en la colección para el throttle condicional.
     *
     * @param Collection $registros
     */
    private function getTotalRegistros($registros): int
    {
        return $registros->count();
    }
}
