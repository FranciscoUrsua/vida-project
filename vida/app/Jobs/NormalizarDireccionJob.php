<?php

namespace App\Jobs;

use App\Enums\OrigenDireccion;
use App\Models\Ciudadano;
use App\Observers\DireccionObserver;
use App\Services\Geocodificacion\GeocodificadorInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Centro\Models\Centro;

/**
 * Job de reintento de normalización de dirección.
 *
 * Procesa entidades con direccion_normalizada = false intentando geocodificarlas
 * de nuevo. Se ejecuta en cola de baja prioridad ('low') con backoff exponencial.
 *
 * El job es genérico: recibe la clase del modelo y el id, no está acoplado a
 * Ciudadano ni a Centro. Cualquier modelo que use TieneDireccion puede encolarlo.
 *
 * Si el reintento tiene éxito, actualiza los campos canónicos y establece
 * origen_direccion = geocodificacion. Si falla, el job se reencola automáticamente
 * hasta agotar los intentos máximos.
 *
 * Ver docs/geocodificacion.md § 4.2.
 *
 * @see DireccionObserver
 */
class NormalizarDireccionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Número máximo de intentos antes de declarar el fallo permanente. */
    public int $tries = 5;

    /** Evita que el job se marque como fallido hasta agotar todos los intentos. */
    public int $maxExceptions = 3;

    /**
     * @param string $claseModelo FQCN del modelo a normalizar.
     * @param int|string $modeloId Identificador del registro.
     */
    public function __construct(
        private readonly string $claseModelo,
        private readonly int|string $modeloId,
    ) {}

    /**
     * Segundos de espera entre reintentos; backoff exponencial.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 120, 300, 600];
    }

    /**
     * Procesa el reintento de normalización.
     *
     * Recupera el modelo, verifica que aún necesita normalización y llama
     * al geocoder. Si tiene éxito, guarda los campos estructurados sin pasar
     * por el DireccionObserver para evitar bucle.
     *
     * @param GeocodificadorInterface $geocodificador Servicio de geocodificación.
     */
    public function handle(GeocodificadorInterface $geocodificador): void
    {
        /** @var Ciudadano|Centro|null $modelo */
        $modelo = ($this->claseModelo)::find($this->modeloId);

        if ($modelo === null || $modelo->direccion_normalizada || empty($modelo->direccion_texto)) {
            return;
        }

        $resultado = $geocodificador->normalizar((string) $modelo->direccion_texto);

        if (! $resultado->exito) {
            $this->fail(new \RuntimeException($resultado->errorMensaje ?? 'Error desconocido del geocoder'));

            return;
        }

        $modelo::withoutEvents(function () use ($modelo, $resultado): void {
            $modelo->update([
                'direccion_normalizada' => true,
                'tipo_via' => $resultado->tipoVia,
                'nombre_via' => $resultado->nombreVia,
                'tipo_numeracion' => $resultado->tipoNumeracion,
                'numero' => $resultado->numero,
                'portal' => $resultado->portal,
                'escalera' => $resultado->escalera,
                'piso' => $resultado->piso,
                'puerta' => $resultado->puerta,
                'codigo_postal' => $resultado->codigoPostal ?? $modelo->codigo_postal,
                'municipio' => $resultado->municipio,
                'coordenadas_lat' => $resultado->latitud,
                'coordenadas_lng' => $resultado->longitud,
                'geocoder_proveedor' => $resultado->proveedor,
                'origen_direccion' => OrigenDireccion::Geocodificacion,
            ]);
        });
    }
}
