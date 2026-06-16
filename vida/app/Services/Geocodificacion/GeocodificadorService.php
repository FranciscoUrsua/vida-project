<?php

namespace App\Services\Geocodificacion;

use App\Services\Geocodificacion\Adaptadores\MockGeocodificador;
use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * Fachada del servicio de geocodificación.
 *
 * Lee el proveedor activo de la configuración del sistema y delega en el
 * adaptador correspondiente. Los módulos funcionales inyectan
 * GeocodificadorInterface y nunca instancian adaptadores directamente.
 *
 * El proveedor se configura en backoffice con la clave 'geocoder.proveedor'.
 * Cambiar de proveedor no requiere código ni despliegue.
 *
 * Ver docs/geocodificacion.md § 2.3.
 */
class GeocodificadorService implements GeocodificadorInterface
{
    /**
     * Adaptadores disponibles, identificados por su clave de configuración.
     *
     * @var array<string, class-string<GeocodificadorInterface>>
     */
    private const ADAPTADORES = [
        'mock' => MockGeocodificador::class,
        // 'bdc'   => BdcGeocodificador::class,   // pendiente
        // 'osm'   => OsmGeocodificador::class,    // pendiente
        // 'gmaps' => GmapsGeocodificador::class,  // pendiente
    ];

    /**
     * Normaliza la dirección delegando en el adaptador activo.
     *
     * @param string $direccionTexto Texto libre tal como lo introduce el profesional.
     *
     * @return ResultadoGeocodificacion Resultado uniforme devuelto por el adaptador activo.
     */
    public function normalizar(string $direccionTexto): ResultadoGeocodificacion
    {
        $proveedor = configuracion_sistema('geocoder.proveedor', 'mock');
        $adaptador = $this->resolverAdaptador((string) $proveedor);

        return $adaptador->normalizar($direccionTexto);
    }

    /**
     * Resuelve el adaptador correspondiente a la clave de proveedor.
     *
     * Si el proveedor configurado no tiene adaptador registrado, cae al mock
     * para no bloquear el sistema.
     *
     * @param string $proveedor Clave de configuración del proveedor.
     */
    private function resolverAdaptador(string $proveedor): GeocodificadorInterface
    {
        $clase = self::ADAPTADORES[$proveedor] ?? self::ADAPTADORES['mock'];

        try {
            return app($clase);
        } catch (BindingResolutionException) {
            return new MockGeocodificador;
        }
    }
}
