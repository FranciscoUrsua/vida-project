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
 * @property-read string|null         $codigoNdp      Identificador del portal (NDP de la BDC)
 * @property-read string|null         $codigoDistrito Dos dígitos, ej: 21
 * @property-read string|null         $codigoBarrio   Código municipal completo, ej: 214
 * @property-read string|null         $seccionCensal  Código INE de 10 dígitos, ej: 2807921028
 *
 * Los cuatro códigos territoriales llegan ya normalizados: el adaptador es
 * quien compone el código de barrio completo y el código INE de la sección a
 * partir de lo que devuelva su proveedor (docs/modulo-asignacion.md §2.2).
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
     * @param string|null $codigoNdp Identificador del portal.
     * @param string|null $codigoDistrito Código de distrito (dos dígitos).
     * @param string|null $codigoBarrio Código de barrio completo (tres dígitos).
     * @param string|null $seccionCensal Código INE de la sección (diez dígitos).
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
        public readonly ?string $codigoNdp = null,
        public readonly ?string $codigoDistrito = null,
        public readonly ?string $codigoBarrio = null,
        public readonly ?string $seccionCensal = null,
    ) {}

    /**
     * Crea un resultado de fallo, sin dirección, coordenadas ni códigos territoriales.
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
            codigoNdp: null,
            codigoDistrito: null,
            codigoBarrio: null,
            seccionCensal: null,
        );
    }

    /**
     * Códigos territoriales como atributos de las columnas del modelo de dirección.
     *
     * @return array{codigo_ndp: string|null, distrito_codigo: string|null, barrio_codigo: string|null, seccion_censal_codigo: string|null}
     */
    public function codigosTerritoriales(): array
    {
        return [
            'codigo_ndp' => $this->codigoNdp,
            'distrito_codigo' => $this->codigoDistrito,
            'barrio_codigo' => $this->codigoBarrio,
            'seccion_censal_codigo' => $this->seccionCensal,
        ];
    }
}
