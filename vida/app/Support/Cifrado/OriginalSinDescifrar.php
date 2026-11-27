<?php

namespace App\Support\Cifrado;

use Illuminate\Database\Eloquent\Model;

/**
 * Valores originales de un modelo con los atributos cifrados **tal como están en la BD**.
 *
 * `getOriginal()` devuelve los valores casteados, es decir, descifrados. Quien copia el
 * estado anterior a otra tabla (versiones, auditoría) lo dejaría en claro y saltaría la
 * regla de CLAUDE.md §3 (datos personales cifrados sin excepción). Aquí los atributos con
 * cast `encrypted`, `encrypted:array`, `encrypted:collection`… se toman sin descifrar.
 */
final class OriginalSinDescifrar
{
    /**
     * Originales del modelo con los cifrados sin descifrar.
     *
     * @param Model $modelo Modelo que se va a actualizar.
     *
     * @return array<string, mixed>
     */
    public static function de(Model $modelo): array
    {
        $originales = $modelo->getOriginal();

        foreach (self::atributosCifrados($modelo) as $atributo) {
            if (array_key_exists($atributo, $originales)) {
                $originales[$atributo] = $modelo->getRawOriginal($atributo);
            }
        }

        return $originales;
    }

    /**
     * Atributos del modelo con un cast cifrado.
     *
     * @param Model $modelo
     *
     * @return list<string>
     */
    public static function atributosCifrados(Model $modelo): array
    {
        $cifrados = [];
        foreach ($modelo->getCasts() as $atributo => $cast) {
            if (is_string($cast) && str_starts_with($cast, 'encrypted')) {
                $cifrados[] = $atributo;
            }
        }

        return $cifrados;
    }
}
