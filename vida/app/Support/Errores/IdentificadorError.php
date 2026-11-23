<?php

namespace App\Support\Errores;

use Illuminate\Support\Str;

/**
 * Identificador de error de la petición en curso: el mismo código se muestra al usuario
 * en la pantalla de error y se escribe en el log, para encontrar el registro a partir
 * de lo que el usuario comunica (R-03 de la ronda 1 de staging).
 */
class IdentificadorError
{
    /** Atributo de la petición donde se guarda el identificador. */
    private const ATRIBUTO = 'vida_error_id';

    /**
     * Identificador de la petición en curso; se genera la primera vez que se pide.
     *
     * Formato corto y legible por teléfono: `ERR-` y ocho caracteres en mayúsculas.
     *
     * @return string
     */
    public static function actual(): string
    {
        $peticion = request();
        $id = $peticion->attributes->get(self::ATRIBUTO);

        if (! is_string($id)) {
            $id = 'ERR-'.Str::upper(Str::random(8));
            $peticion->attributes->set(self::ATRIBUTO, $id);
        }

        return $id;
    }

    /**
     * Si en esta petición ya se ha generado un identificador (se ha pintado una pantalla de error).
     *
     * @return bool
     */
    public static function generado(): bool
    {
        return is_string(request()->attributes->get(self::ATRIBUTO));
    }
}
