<?php

namespace App\Services\Geocodificacion;

use App\Services\Geocodificacion\Adaptadores\MockGeocodificador;
use App\Services\Geocodificacion\Adaptadores\SinGeocodificador;
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
 * El simulador (`mock`) inventa coordenadas y sección censal: solo se usa en los
 * tests. Fuera de ellos, si no hay un proveedor real configurado, el adaptador es
 * `ninguno` (SinGeocodificador) y la dirección queda como texto libre sin
 * normalizar (decisión del 25 nov 2026).
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
        SinGeocodificador::IDENTIFICADOR => SinGeocodificador::class,
        'mock' => MockGeocodificador::class,
        // 'callejero' => CallejeroMadridGeocodificador::class, // pendiente (callejero oficial de Madrid)
        // 'bdc'   => BdcGeocodificador::class,   // pendiente
    ];

    /**
     * Normaliza la dirección delegando en el adaptador activo.
     *
     * @param string $direccionTexto Texto libre tal como lo introduce el profesional.
     *
     * @return ResultadoGeocodificacion
     */
    public function normalizar(string $direccionTexto): ResultadoGeocodificacion
    {
        return $this->resolverAdaptador(self::proveedorActivo())->normalizar($direccionTexto);
    }

    /**
     * Proveedor que se usa de verdad: el configurado si tiene adaptador; el simulador
     * solo en los tests; en cualquier otro caso, `ninguno`.
     *
     * @return string
     */
    public static function proveedorActivo(): string
    {
        $porDefecto = app()->environment('testing') ? 'mock' : SinGeocodificador::IDENTIFICADOR;
        $configurado = (string) configuracion_sistema('geocoder.proveedor', $porDefecto);

        if (! isset(self::ADAPTADORES[$configurado])) {
            return $porDefecto;
        }

        // El simulador inventa datos territoriales: nunca fuera de los tests
        if ($configurado === 'mock' && ! app()->environment('testing')) {
            return SinGeocodificador::IDENTIFICADOR;
        }

        return $configurado;
    }

    /**
     * Si hay un geocodificador que pueda normalizar. Sin él no tiene sentido encolar
     * reintentos: fallarían siempre.
     *
     * @return bool
     */
    public static function hayGeocodificador(): bool
    {
        return self::proveedorActivo() !== SinGeocodificador::IDENTIFICADOR;
    }

    /**
     * Resuelve el adaptador correspondiente a la clave de proveedor.
     *
     * @param string $proveedor Clave de configuración del proveedor.
     *
     * @return GeocodificadorInterface
     */
    private function resolverAdaptador(string $proveedor): GeocodificadorInterface
    {
        try {
            return app(self::ADAPTADORES[$proveedor]);
        } catch (BindingResolutionException) {
            return new SinGeocodificador;
        }
    }
}
