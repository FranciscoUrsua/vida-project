<?php

namespace Modules\Mensajes\Exceptions;

use RuntimeException;

/**
 * Excepción lanzada cuando un usuario intenta realizar una acción
 * para la que no tiene autorización dentro del módulo de Mensajes.
 *
 * Ejemplo: intentar registrar un mensaje en la Historia Social de un
 * ciudadano del que no se es TSR responsable.
 */
class UnauthorizedException extends RuntimeException
{
    /**
     * Construye la excepción cuando el usuario no es TSR responsable.
     *
     * @param int $usuarioId ID del usuario.
     * @param int $ciudadanoId ID del ciudadano.
     */
    public static function noEsTsr(int $usuarioId, int $ciudadanoId): self
    {
        return new self(
            "El usuario #{$usuarioId} no es el TSR responsable del expediente del ciudadano #{$ciudadanoId}."
        );
    }

    /**
     * Construye la excepción cuando el usuario no tiene el rol de supervisión.
     *
     * @param int $usuarioId ID del usuario.
     * @return self
     */
    public static function noEsSupervisor(int $usuarioId): self
    {
        return new self("El usuario #{$usuarioId} no tiene el rol de supervisión.");
    }

    /**
     * Construye la excepción cuando el supervisor no está adscrito a la UO.
     *
     * @param int $usuarioId ID del supervisor.
     * @param int $uoId ID de la UO.
     * @return self
     */
    public static function uoAjena(int $usuarioId, int $uoId): self
    {
        return new self("El usuario #{$usuarioId} no puede enviar avisos a la UO #{$uoId}: no es su UO.");
    }
}
