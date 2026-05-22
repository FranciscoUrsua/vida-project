<?php

namespace App\Exceptions\Anonimizacion;

use RuntimeException;

/**
 * La validación final de k-anonimato falló: existen combinaciones de
 * cuasi-identificadores con menos de K registros tras aplicar la cascada completa.
 *
 * Este error bloquea la entrega del fichero de extracción. El job queda
 * en estado 'error_k_anonimato'. Ver docs/anonimizacion.md § 6.2.
 */
class KAnonimatoValidacionException extends RuntimeException
{
    /**
     * @param array<string> $combinacionesProblematicas Claves serializada de las combinaciones < K
     */
    public function __construct(private readonly array $combinacionesProblematicas)
    {
        $n = count($combinacionesProblematicas);
        parent::__construct("Validación de k-anonimato fallida: {$n} combinación(es) con frecuencia insuficiente tras la cascada completa");
    }

    /**
     * Devuelve el detalle de las combinaciones que no alcanzaron K.
     *
     * @return array<string>
     */
    public function getCombinaciones(): array
    {
        return $this->combinacionesProblematicas;
    }
}
