<?php

namespace App\Exceptions\Anonimizacion;

use RuntimeException;

/**
 * El perfil de anonimización solicitado existe pero está inactivo.
 */
class PerfilAnonimizacionInactivoException extends RuntimeException
{
    /**
     * @param string $perfilId Identificador del perfil inactivo
     */
    public function __construct(string $perfilId)
    {
        parent::__construct("El perfil de anonimización '{$perfilId}' está inactivo");
    }
}
