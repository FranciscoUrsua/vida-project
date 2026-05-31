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

if (! function_exists('saludo')) {
    /**
     * Devuelve un saludo apropiado según la hora del día.
     * Evita el uso de género en la bienvenida.
     */
    function saludo(): string
    {
        $hora = now()->hour;

        return match(true) {
            $hora >= 6 && $hora < 14  => 'Buenos días',
            $hora >= 14 && $hora < 21 => 'Buenas tardes',
            default                    => 'Buenas noches',
        };
    }
}
