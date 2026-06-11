<?php

namespace Modules\Ciudadania\Contracts;

/**
 * Contrato del servicio de consulta al padrón municipal.
 *
 * Toda la aplicación interactúa con el padrón a través de esta interfaz.
 * El adaptador concreto es intercambiable. Por defecto se usa MockFuenteIdentidad.
 *
 * RESTRICCIÓN DE SEGURIDAD: Para ciudadanas en circuito VVG, esta interfaz
 * no debe invocarse en ningún momento, ni siquiera para ignorar la respuesta.
 * La condición se evalúa ANTES de cualquier llamada. Ver principio 4.1.
 *
 * Ver docs/modulo-ciudadania.md § 7.3.
 */
interface FuenteIdentidadInterface
{
    /**
     * Consulta los datos de identidad de una persona por su documento.
     *
     * @param string $valorDocumento Documento normalizado (NIF, NIE o pasaporte).
     *
     * @return array{nombre: string, apellido1: string, apellido2: ?string, fecha_nacimiento: string, sexo: string, direccion_texto: ?string}|null
     *                                                                                                                                             Datos del padrón o null si la persona no está empadronada.
     */
    public function consultarDatos(string $valorDocumento): ?array;
}
