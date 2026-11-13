<?php

namespace Modules\Intervencion\Services\Asignacion;

use DomainException;

/**
 * Rechazo de una salida de centro o de una desactivación por casos o citas
 * pendientes (RN-21 y RN-22). Lleva el detalle por centro y los enlaces a
 * «Repartir casos» y a la gestión de la ausencia para que la interfaz los ofrezca.
 */
class SalidaCentroBloqueada extends DomainException
{
    /**
     * @param list<PendientesSalida> $pendientes Centros con algo pendiente.
     * @param string $accion «salir del centro» o «desactivar al usuario», para el mensaje.
     */
    public function __construct(public readonly array $pendientes, string $accion)
    {
        $detalle = implode('; ', array_map(fn (PendientesSalida $p) => $p->descripcion(), $pendientes));

        parent::__construct("No se puede {$accion} mientras tenga casos o citas pendientes ({$detalle}). "
            .'Reparte antes sus casos y reasigna sus citas desde la gestión de ausencias.');
    }

    /**
     * Enlaces para resolver lo pendiente: el reparto por salida (pestaña de
     * actividad de Asignaciones) si hay casos y la gestión de ausencias si hay citas.
     *
     * @return array<string, string> Etiqueta => URL.
     */
    public function enlaces(): array
    {
        $enlaces = [];

        if (collect($this->pendientes)->sum('casos') > 0) {
            $enlaces['Repartir casos'] = route('supervision.asignaciones', ['pestana' => 'actividad']);
        }

        if (collect($this->pendientes)->sum('citas') > 0) {
            $enlaces['Reasignar citas'] = route('agenda.supervisor.ausencias');
        }

        return $enlaces;
    }
}
