<?php

namespace App\Services\Geocodificacion;

use App\Enums\TipoNumeracion;

/**
 * Partes de una dirección escrita a mano, tal como las entiende `ParserDireccion`.
 *
 * Es solo el análisis del texto: no dice si la dirección existe. Los adaptadores la
 * contrastan (el del callejero) o la dan por buena (el simulador de los tests).
 */
final class DireccionAnalizada
{
    /**
     * Crea el análisis con las partes reconocidas.
     *
     * @param string|null $tipoVia Tipo de vía escrito («Calle», «Avenida»…), o null si el texto no lo dice.
     * @param string $nombreVia Nombre de la vía tal como se escribió, sin tipo ni número.
     * @param TipoNumeracion|null $tipoNumeracion Número, sin número o kilómetro; null si no hay.
     * @param string|null $numero Número (solo cifras, o con «bis»), sin calificador.
     * @param string|null $calificador Letra pegada al número («15B» → «B»).
     * @param string|null $portal
     * @param string|null $escalera
     * @param string|null $piso
     * @param string|null $puerta
     * @param string|null $codigoPostal Código postal de Madrid (28xxx) si aparece.
     * @param string|null $localidad Localidad escrita tras el número («Alcalá de Henares»), si la hay.
     */
    public function __construct(
        public readonly ?string $tipoVia,
        public readonly string $nombreVia,
        public readonly ?TipoNumeracion $tipoNumeracion,
        public readonly ?string $numero,
        public readonly ?string $calificador,
        public readonly ?string $portal,
        public readonly ?string $escalera,
        public readonly ?string $piso,
        public readonly ?string $puerta,
        public readonly ?string $codigoPostal,
        public readonly ?string $localidad,
    ) {}
}
