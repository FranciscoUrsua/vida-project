<?php

namespace Modules\Agenda\Enums;

enum EstadoSlot: string
{
    case Disponible = 'disponible';
    case Reservado = 'reservado';
    case BloqueadoUrgencia = 'bloqueado_urgencia';
    case BloqueadoEvento = 'bloqueado_evento';
    case Anulado = 'anulado';
    case Expirado = 'expirado';
    case NoOcupado = 'no_ocupado';

    public function label(): string
    {
        return match ($this) {
            self::Disponible => 'Disponible',
            self::Reservado => 'Reservado',
            self::BloqueadoUrgencia => 'Bloqueado (urgencia)',
            self::BloqueadoEvento => 'Bloqueado (evento)',
            self::Anulado => 'Anulado',
            self::Expirado => 'Expirado',
            self::NoOcupado => 'No ocupado',
        };
    }
}
