<?php

namespace App\Console\Commands;

use App\Enums\OrigenDireccion;
use App\Events\DireccionCiudadanoNormalizada;
use App\Models\Ciudadano;
use App\Services\Geocodificacion\Adaptadores\CallejeroMadridGeocodificador;
use App\Services\Geocodificacion\GeocodificadorInterface;
use App\Services\Geocodificacion\GeocodificadorService;
use Illuminate\Console\Command;
use Modules\Centro\Models\Centro;

/**
 * Comando artisan para normalización masiva de direcciones pendientes.
 *
 * Procesa en batches de 100 registros. Con un proveedor externo espera 1 segundo
 * entre batches para no saturarlo; con el callejero local no hace falta.
 *
 * Solo toca direcciones escritas por un profesional (o sin origen, de cargas
 * antiguas): las del padrón no pasan por el geocodificador (geocodificacion.md
 * § 3.3). Una dirección que casa guarda también los códigos territoriales y, en
 * una persona, dispara la asignación de centro, igual que el reintento
 * (NormalizarDireccionJob). Corregido el 25 nov 2026: antes no guardaba los
 * códigos ni avisaba a la asignación, y procesaba también las del padrón.
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
     * @param GeocodificadorInterface $geocodificador Servicio de geocodificación.
     */
    public function handle(GeocodificadorInterface $geocodificador): int
    {
        $entidadClave = $this->option('entidad');
        $soloPendientes = (bool) $this->option('pendientes');

        if (! GeocodificadorService::hayGeocodificador()) {
            $this->warn('No hay geocodificador configurado (geocoder.proveedor): no se normaliza nada.');

            return self::SUCCESS;
        }

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

        // Proceso del sistema: sin el filtro de ámbito del usuario
        $consulta = $claseModelo::query()->withoutGlobalScopes()
            ->where(fn ($q) => $q->whereNull('origen_direccion')->orWhere('origen_direccion', OrigenDireccion::Profesional));

        if ($soloPendientes) {
            $consulta->sinNormalizar();
        }

        $pausar = GeocodificadorService::proveedorActivo() !== CallejeroMadridGeocodificador::IDENTIFICADOR;
        $motivos = [];

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
            $geocodificador, &$procesados, &$exitosos, &$fallidos, &$motivos, $barra, $pausar
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
                        // Sin eventos para no volver a geocodificar; la asignación se avisa aparte
                        $registro->withoutEvents(fn () => $registro->update([
                            ...$resultado->atributosDireccion(),
                            'codigo_postal' => $resultado->codigoPostal ?? $registro->codigo_postal,
                            'origen_direccion' => OrigenDireccion::Geocodificacion,
                        ]));

                        if ($registro instanceof Ciudadano) {
                            DireccionCiudadanoNormalizada::dispatch($registro);
                        }

                        $exitosos++;
                    } else {
                        $fallidos++;
                        // Motivo sin los datos de la dirección, para el resumen
                        $motivo = (string) preg_replace('/\s*\(.*\)|\d+/', '', (string) $resultado->errorMensaje);
                        $motivos[$motivo] = ($motivos[$motivo] ?? 0) + 1;
                    }
                } catch (\Throwable) {
                    $fallidos++;
                }

                $procesados++;
                $barra->advance();
            }

            // Pausa entre batches solo con un proveedor externo
            if ($pausar) {
                sleep(1);
            }
        });

        $barra->finish();
        $this->newLine();
        $this->line("  Exitosos: {$exitosos} | Fallidos: {$fallidos} | Total: {$procesados}");

        arsort($motivos);
        foreach ($motivos as $motivo => $cuantos) {
            $this->line("    {$cuantos} × {$motivo}");
        }
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
}
