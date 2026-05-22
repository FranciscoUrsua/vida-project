<?php

namespace App\Exceptions\Anonimizacion;

use RuntimeException;

/**
 * Intento de eliminar un perfil de anonimización predefinido del sistema.
 *
 * Los perfiles de sistema (es_sistema = true) son invariantes del contrato
 * del sistema. No pueden eliminarse sin una decisión explícita documentada.
 */
class PerfilSistemaNoEliminableException extends RuntimeException
{
    /**
     * @param string $perfilNombre Nombre del perfil que se intentó eliminar
     */
    public function __construct(string $perfilNombre)
    {
        parent::__construct("El perfil '{$perfilNombre}' es un perfil de sistema y no puede eliminarse");
    }
}
