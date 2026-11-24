<?php

namespace App\Services\Geocodificacion\Adaptadores;

use App\Services\Geocodificacion\GeocodificadorInterface;
use App\Services\Geocodificacion\ResultadoGeocodificacion;

/**
 * Adaptador «ninguno»: no hay geocodificador real configurado.
 *
 * Devuelve siempre «no normalizada», sin datos territoriales. Es el adaptador por
 * defecto fuera de los tests desde el 25 nov 2026: antes se usaba el simulador
 * (MockGeocodificador), que daba por buena cualquier dirección e inventaba
 * coordenadas y sección censal, con la que la asignación de centro por domicilio
 * habría decidido en silencio con datos falsos. Sin sección, la asignación queda
 * pendiente de hacerse a mano.
 */
class SinGeocodificador implements GeocodificadorInterface
{
    /** Identificador del adaptador en `geocoder.proveedor` y `geocoder_proveedor`. */
    public const IDENTIFICADOR = 'ninguno';

    /**
     * No normaliza: deja la dirección como texto libre.
     *
     * @param string $direccionTexto
     *
     * @return ResultadoGeocodificacion
     */
    public function normalizar(string $direccionTexto): ResultadoGeocodificacion
    {
        return ResultadoGeocodificacion::fallo(self::IDENTIFICADOR, 'No hay geocodificador configurado.');
    }
}
