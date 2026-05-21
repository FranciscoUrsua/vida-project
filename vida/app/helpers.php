<?php

use Modules\Organizacion\Models\Configuracion;

if (!function_exists('configuracion_sistema')) {
    /**
     * Lee un parámetro de configuración del sistema desde la tabla organizacion_configuracion.
     *
     * Devuelve el valor casteado según el tipo declarado en el registro (texto, numero,
     * booleano, json). Si la clave no existe o la tabla no está disponible (p.ej. durante
     * tests con BD vacía), devuelve el valor por defecto.
     *
     * @param  string $clave    Clave del parámetro, p. ej. 'geocoder.proveedor'.
     * @param  mixed  $default  Valor por defecto si la clave no existe.
     * @return mixed
     */
    function configuracion_sistema(string $clave, mixed $default = null): mixed
    {
        try {
            $config = Configuracion::where('clave', $clave)->first();
            return $config !== null ? $config->valorCasteado() : $default;
        } catch (\Throwable) {
            return $default;
        }
    }
}
