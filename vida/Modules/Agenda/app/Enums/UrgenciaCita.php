<?php

namespace Modules\Agenda\Enums;

use App\Support\Ui\Tono;

/**
 * Urgencia de una solicitud de cita. Decide el plazo máximo (plazos_urgencia del
 * horario del centro) y si se pueden consumir slots reservados para urgencias.
 */
enum UrgenciaCita: string
{
    case Ordinaria = 'ordinaria';
    case Preferente = 'preferente';
    case Urgente = 'urgente';

    /**
     * Etiqueta para la interfaz.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Ordinaria => 'Ordinaria',
            self::Preferente => 'Preferente',
            self::Urgente => 'Urgente',
        };
    }

    /**
     * Tono con que se pinta la urgencia.
     *
     * @return Tono
     */
    public function tono(): Tono
    {
        return match ($this) {
            self::Ordinaria => Tono::Neutro,
            self::Preferente => Tono::Aviso,
            self::Urgente => Tono::Peligro,
        };
    }

    /**
     * Si puede consumir slots reservados para urgencias.
     *
     * @return bool
     */
    public function admiteSlotsUrgencia(): bool
    {
        return $this === self::Urgente;
    }

    /**
     * Orden de prioridad en la bandeja (menor, antes).
     *
     * @return int
     */
    public function prioridad(): int
    {
        return match ($this) {
            self::Urgente => 0,
            self::Preferente => 1,
            self::Ordinaria => 2,
        };
    }
}
