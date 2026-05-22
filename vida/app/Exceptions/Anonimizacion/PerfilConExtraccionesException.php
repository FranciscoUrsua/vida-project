<?php

namespace App\Exceptions\Anonimizacion;

use RuntimeException;

/**
 * Intento de eliminar un perfil de anonimización que tiene extracciones asociadas.
 *
 * Un perfil con extracciones no puede eliminarse porque esas extracciones
 * referencian la versión exacta del perfil que se les aplicó, lo que garantiza
 * la trazabilidad de cualquier extracción pasada. Ver docs/anonimizacion.md § 6.4.
 */
class PerfilConExtraccionesException extends RuntimeException
{
    /**
     * @param string $perfilNombre Nombre del perfil con extracciones asociadas
     */
    public function __construct(string $perfilNombre)
    {
        parent::__construct("El perfil '{$perfilNombre}' tiene extracciones asociadas y no puede eliminarse");
    }
}
