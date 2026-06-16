<?php

namespace App\Observers;

use App\Enums\OrigenDireccion;
use App\Jobs\NormalizarDireccionJob;
use App\Services\Geocodificacion\GeocodificadorInterface;
use App\Services\Geocodificacion\ResultadoGeocodificacion;
use Illuminate\Database\Eloquent\Model;

/**
 * Observer de dirección para modelos que usan el trait TieneDireccion.
 *
 * Invoca el geocoder al guardar una entidad con dirección introducida
 * manualmente (origen_direccion = profesional). Si el geocoder falla o
 * supera el timeout, guarda con direccion_normalizada = false y encola
 * un job de reintento sin bloquear el guardado.
 *
 * Las direcciones procedentes del padrón (origen_direccion = padron)
 * llegan ya estructuradas y no pasan por el geocoder.
 *
 * Ver docs/geocodificacion.md § 4.1.
 */
class DireccionObserver
{
    /** Timeout en segundos para la llamada al geocoder. */
    private const TIMEOUT_SEGUNDOS = 3;

    /**
     * Crea el observer con el adaptador de geocodificacion configurado.
     *
     * @param GeocodificadorInterface $geocodificador Servicio usado para normalizar direcciones.
     */
    public function __construct(
        private readonly GeocodificadorInterface $geocodificador,
    ) {}

    /**
     * Intenta geocodificar antes de insertar el registro.
     *
     * Inicializa siempre direccion_normalizada a false para que el modelo
     * en memoria refleje el default de la columna si no se geocodifica.
     *
     * @param Model $model Modelo Eloquent con campos del trait TieneDireccion.
     *
     * @return void
     */
    public function creating(Model $model): void
    {
        if (! array_key_exists('direccion_normalizada', $model->getAttributes())) {
            $model->direccion_normalizada = false;
        }
        $this->intentarNormalizar($model);
    }

    /**
     * Encola el job de reintento si el guardado inicial no normalizó la dirección.
     *
     * @param Model $model Modelo Eloquent con campos del trait TieneDireccion.
     *
     * @return void
     */
    public function created(Model $model): void
    {
        $this->encolarSiPendiente($model);
    }

    /**
     * Intenta geocodificar antes de actualizar el registro.
     *
     * Solo actúa si cambió el texto de la dirección o el origen.
     *
     * @param Model $model Modelo Eloquent con campos del trait TieneDireccion.
     *
     * @return void
     */
    public function updating(Model $model): void
    {
        if (! $model->isDirty(['direccion_texto', 'origen_direccion'])) {
            return;
        }
        $this->intentarNormalizar($model);
    }

    /**
     * Encola el job de reintento si la actualización no normalizó la dirección.
     *
     * @param Model $model Modelo Eloquent con campos del trait TieneDireccion.
     *
     * @return void
     */
    public function updated(Model $model): void
    {
        $this->encolarSiPendiente($model);
    }

    // -------------------------------------------------------------------------
    // Lógica interna
    // -------------------------------------------------------------------------

    /**
     * Intenta normalizar la dirección del modelo invocando el geocoder.
     *
     * Solo actúa cuando origen_direccion es 'profesional' y hay texto de dirección.
     * Si el geocoder falla, marca direccion_normalizada = false sin lanzar excepción.
     *
     * El timeout de 3 segundos se aplica a nivel de conexión HTTP en los adaptadores
     * reales. El mock responde instantáneamente.
     */
    private function intentarNormalizar(Model $model): void
    {
        if (! $this->debeGeocodificar($model)) {
            return;
        }

        try {
            // El timeout lo gestiona el adaptador HTTP. Aquí capturamos cualquier fallo.
            $resultado = $this->geocodificador->normalizar((string) $model->direccion_texto);
            $this->aplicarResultado($model, $resultado);
        } catch (\Throwable) {
            $model->direccion_normalizada = false;
        }
    }

    /**
     * Encola NormalizarDireccionJob si la dirección sigue pendiente de normalización.
     */
    private function encolarSiPendiente(Model $model): void
    {
        if (
            $this->debeGeocodificar($model) &&
            ! $model->direccion_normalizada
        ) {
            NormalizarDireccionJob::dispatch(get_class($model), $model->getKey())
                ->onQueue('low');
        }
    }

    /**
     * Determina si el modelo debe pasar por el geocoder.
     *
     * Solo geocodifica cuando el origen es 'profesional' y hay texto de dirección.
     */
    private function debeGeocodificar(Model $model): bool
    {
        return $model->origen_direccion === OrigenDireccion::Profesional
            && ! empty($model->direccion_texto);
    }

    /**
     * Aplica el resultado del geocoder a los atributos del modelo en memoria.
     *
     * No llama a save() — el resultado se persistirá con el save() que desencadenó
     * el evento creating/updating.
     */
    private function aplicarResultado(Model $model, ResultadoGeocodificacion $resultado): void
    {
        if (! $resultado->exito) {
            $model->direccion_normalizada = false;

            return;
        }

        $model->direccion_normalizada = true;
        $model->tipo_via = $resultado->tipoVia;
        $model->nombre_via = $resultado->nombreVia;
        $model->tipo_numeracion = $resultado->tipoNumeracion;
        $model->numero = $resultado->numero;
        $model->portal = $resultado->portal;
        $model->escalera = $resultado->escalera;
        $model->piso = $resultado->piso;
        $model->puerta = $resultado->puerta;
        $model->codigo_postal = $resultado->codigoPostal ?? $model->codigo_postal;
        $model->municipio = $resultado->municipio;
        $model->coordenadas_lat = $resultado->latitud;
        $model->coordenadas_lng = $resultado->longitud;
        $model->geocoder_proveedor = $resultado->proveedor;
    }
}
