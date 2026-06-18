<?php

namespace App\Services\Geocodificacion;

use App\Enums\TipoNumeracion;

/**
 * Resultado inmutable de una operación de geocodificación.
 *
 * Estructura uniforme independientemente del adaptador que procesó la petición.
 * Si $exito es false, los campos de dirección y coordenadas pueden ser null;
 * $errorMensaje describe el motivo del fallo.
 *
 * Ver docs/geocodificacion.md § 2.1.
 *
 * @property-read bool                $exito
 * @property-read string|null         $tipoVia        Calle, Avenida, Plaza, Paseo...
 * @property-read string|null         $nombreVia      Gran Vía, Mayor, Alcalá...
 * @property-read TipoNumeracion|null $tipoNumeracion numero | sin_numero | km
 * @property-read string|null         $numero         Admite "12 bis", "s/n"
 * @property-read string|null         $portal
 * @property-read string|null         $escalera
 * @property-read string|null         $piso
 * @property-read string|null         $puerta
 * @property-read string|null         $codigoPostal
 * @property-read string|null         $municipio
 * @property-read float|null          $latitud        WGS84
 * @property-read float|null          $longitud       WGS84
 * @property-read string              $proveedor      Identificador del adaptador
 * @property-read string|null         $errorMensaje
 */
final class ResultadoGeocodificacion
{
    /**
     * Crea una instancia inmutable con los datos geocodificados.
     *
     * @param bool $exito Indica si la geocodificación fue exitosa.
     * @param string|null $tipoVia Tipo de vía.
     * @param string|null $nombreVia Nombre de la vía.
     * @param TipoNumeracion|null $tipoNumeracion Tipo de numeración.
     * @param string|null $numero Número de portal.
     * @param string|null $portal Portal.
     * @param string|null $escalera Escalera.
     * @param string|null $piso Piso.
     * @param string|null $puerta Puerta.
     * @param string|null $codigoPostal Código postal.
     * @param string|null $municipio Municipio.
     * @param float|null $latitud Latitud.
     * @param float|null $longitud Longitud.
     * @param string $proveedor Identificador del adaptador.
     * @param string|null $errorMensaje Mensaje de error.
     */
    public function __construct(
        public readonly bool $exito,
        public readonly ?string $tipoVia,
        public readonly ?string $nombreVia,
        public readonly ?TipoNumeracion $tipoNumeracion,
        public readonly ?string $numero,
        public readonly ?string $portal,
        public readonly ?string $escalera,
        public readonly ?string $piso,
        public readonly ?string $puerta,
        public readonly ?string $codigoPostal,
        public readonly ?string $municipio,
        public readonly ?float $latitud,
        public readonly ?float $longitud,
        public readonly string $proveedor,
        public readonly ?string $errorMensaje = null,
    ) {}

    /**
     * Crea un resultado de fallo.
     *
     * @param string $proveedor Identificador del adaptador.
     * @param string $errorMensaje Descripción del fallo.
     * @return self
     */
    public static function fallo(string $proveedor, string $errorMensaje): self
    {
        return new self(
            exito: false,
            tipoVia: null,
            nombreVia: null,
            tipoNumeracion: null,
            numero: null,
            portal: null,
            escalera: null,
            piso: null,
            puerta: null,
            codigoPostal: null,
            municipio: null,
            latitud: null,
            longitud: null,
            proveedor: $proveedor,
            errorMensaje: $errorMensaje,
        );
    }
}
