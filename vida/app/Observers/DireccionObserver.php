<?php

namespace App\Observers;

use App\Enums\OrigenDireccion;
use App\Events\DireccionCiudadanoNormalizada;
use App\Jobs\NormalizarDireccionJob;
use App\Models\Ciudadano;
use App\Services\Geocodificacion\GeocodificadorInterface;
use App\Services\Geocodificacion\ResultadoGeocodificacion;
use Illuminate\Database\Eloquent\Model;
use Modules\Centro\Models\Centro;

/**
 * Observer de dirección para modelos que usan el trait TieneDireccion.
 *
 * Invoca el geocoder al guardar una entidad con dirección introducida
 * manualmente (origen_direccion = profesional). Si el geocoder falla,
 * guarda con direccion_normalizada = false y encola un job de reintento
 * sin bloquear el guardado.
 *
 * Las direcciones procedentes del padrón (origen_direccion = padron)
 * llegan ya estructuradas y no pasan por el geocoder.
 *
 * Tras normalizar la dirección de un Ciudadano dispara
 * DireccionCiudadanoNormalizada, que resuelve su centro por domicilio
 * (docs/modulo-asignacion.md §3.5).
 *
 * Ver docs/geocodificacion.md § 4.1.
 */
class DireccionObserver
{
    /** Códigos territoriales a null, para una dirección que no se ha podido geocodificar. */
    private const CODIGOS_VACIOS = [
        'codigo_ndp' => null,
        'distrito_codigo' => null,
        'barrio_codigo' => null,
        'seccion_censal_codigo' => null,
    ];

    /**
     * @param GeocodificadorInterface $geocodificador Servicio de geocodificación.
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
     * @param Ciudadano|Centro $model Modelo que se va a crear.
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
     * @param Ciudadano|Centro $model Modelo recién creado.
     * @return void
     */
    public function created(Model $model): void
    {
        $this->encolarSiPendiente($model);

        if ($model instanceof Ciudadano && $model->direccion_normalizada) {
            DireccionCiudadanoNormalizada::dispatch($model);
        }
    }

    /**
     * Intenta geocodificar antes de actualizar el registro.
     *
     * Solo actúa si cambió el texto de la dirección o el origen.
     *
     * @param Ciudadano|Centro $model Modelo que se va a actualizar.
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
     * @param Ciudadano|Centro $model Modelo recién actualizado.
     * @return void
     */
    public function updated(Model $model): void
    {
        $this->encolarSiPendiente($model);

        // Solo si esta actualización ha normalizado la dirección (nueva o cambiada)
        if ($model instanceof Ciudadano
            && $model->direccion_normalizada
            && $model->wasChanged(['direccion_texto', 'direccion_normalizada', 'seccion_censal_codigo', 'codigo_ndp'])) {
            DireccionCiudadanoNormalizada::dispatch($model);
        }
    }

    /**
     * Intenta normalizar la dirección del modelo invocando el geocoder.
     *
     * Solo actúa cuando origen_direccion es 'profesional' y hay texto de dirección.
     * Si el geocoder falla, marca direccion_normalizada = false sin lanzar excepción.
     *
     * @param Ciudadano|Centro $model
     */
    private function intentarNormalizar(Model $model): void
    {
        if (! $this->debeGeocodificar($model)) {
            return;
        }

        try {
            $resultado = $this->geocodificador->normalizar((string) $model->direccion_texto);
            $this->aplicarResultado($model, $resultado);
        } catch (\Throwable) {
            $model->direccion_normalizada = false;
            $model->forceFill(self::CODIGOS_VACIOS);
        }
    }

    /**
     * Encola NormalizarDireccionJob si la dirección sigue pendiente de normalización.
     *
     * @param Ciudadano|Centro $model
     */
    private function encolarSiPendiente(Model $model): void
    {
        if ($this->debeGeocodificar($model) && ! $model->direccion_normalizada) {
            NormalizarDireccionJob::dispatch(get_class($model), $model->getKey())
                ->onQueue('low');
        }
    }

    /**
     * Determina si el modelo debe pasar por el geocoder.
     *
     * Solo geocodifica cuando el origen es 'profesional' y hay texto de dirección.
     *
     * @param Ciudadano|Centro $model
     */
    private function debeGeocodificar(Model $model): bool
    {
        return $model->origen_direccion === OrigenDireccion::Profesional
            && ! empty($model->direccion_texto);
    }

    /**
     * Aplica el resultado del geocoder a los atributos del modelo en memoria.
     *
     * No llama a save(); el resultado se persistirá con el save() que desencadenó
     * el evento creating/updating.
     *
     * @param Ciudadano|Centro $model
     */
    private function aplicarResultado(Model $model, ResultadoGeocodificacion $resultado): void
    {
        if (! $resultado->exito) {
            $model->direccion_normalizada = false;
            // Los códigos de una dirección anterior ya no valen para la nueva
            $model->forceFill(self::CODIGOS_VACIOS);

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
        $model->forceFill($resultado->codigosTerritoriales());
    }
}
