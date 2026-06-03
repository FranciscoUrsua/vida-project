<?php

namespace App\Services\Geocodificacion;

/**
 * Contrato del servicio de geocodificación.
 *
 * Toda la aplicación interactúa con el geocoder a través de esta interfaz.
 * El proveedor concreto es un detalle de infraestructura intercambiable
 * desde el backoffice sin necesidad de código ni despliegue.
 *
 * Ver docs/geocodificacion.md § 2.1.
 */
interface GeocodificadorInterface
{
    /**
     * Normaliza una dirección en texto libre extrayendo sus campos estructurados
     * y calculando coordenadas geográficas.
     *
     * @param string $direccionTexto Texto libre tal como lo introduce el profesional.
     *
     * @return ResultadoGeocodificacion Siempre devuelve un resultado — nunca lanza excepción.
     */
    public function normalizar(string $direccionTexto): ResultadoGeocodificacion;
}
