<?php

namespace App\Exceptions\Anonimizacion;

use RuntimeException;

/**
 * El perfil de anonimización solicitado no existe en el sistema.
 */
class PerfilAnonimizacionNotFoundException extends RuntimeException
{
    /**
     * @param string $perfilId Identificador del perfil buscado
     */
    public function __construct(string $perfilId)
    {
        parent::__construct("Perfil de anonimización no encontrado: '{$perfilId}'");
    }
}
