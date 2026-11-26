<?php

namespace Modules\Intervencion\Services\Actuaciones;

use DomainException;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Models\Apunte;

/**
 * Registra anotaciones en la Historia Social, desde la Historia, el plan o un encargo
 * profesional (bloque B de docs/instrucciones-cli/2026-11-encargo-actuaciones.md).
 *
 * Es el único punto de escritura de anotaciones de los profesionales. La anotación privada
 * solo la ve su autor (restricción de dominio, sin excepciones): lo aplican el scope y la
 * policy de `Apunte`, no este servicio.
 */
class AnotacionService
{
    /**
     * Guarda la anotación como apunte de la Historia Social.
     *
     * @param ContextoActuacion $contexto Historia, autor, plan, cita y encargo.
     * @param string $contenido Texto de la anotación.
     * @param VisibilidadApunte $visibilidad Privada o para profesionales.
     *
     * @return Apunte
     *
     * @throws DomainException Si el autor no puede actuar en el centro de la operación (RN-15).
     */
    public function registrar(ContextoActuacion $contexto, string $contenido, VisibilidadApunte $visibilidad): Apunte
    {
        $contexto->exigirActuacion();

        return Apunte::create($contexto->datosApunte() + [
            'tipo' => TipoApunte::Anotacion,
            'contenido' => $contenido,
            'visibilidad' => $visibilidad,
        ]);
    }
}
