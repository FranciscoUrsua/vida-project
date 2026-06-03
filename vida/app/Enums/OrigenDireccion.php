<?php

namespace App\Enums;

/**
 * Origen de la dirección almacenada en una entidad.
 *
 * El DireccionObserver toma decisiones basadas en este valor (solo geocodifica
 * cuando el origen es 'profesional'). Ver docs/geocodificacion.md § 3.3.
 */
enum OrigenDireccion: string
{
    /** Introducida manualmente por un profesional en el sistema. */
    case Profesional = 'profesional';
    /** Importada del padrón municipal — ya viene estructurada con coordenadas. */
    case Padron = 'padron';
    /** Normalizada posteriormente por el job de reintento. */
    case Geocodificacion = 'geocodificacion';

    public function label(): string
    {
        return match ($this) {
            self::Profesional => 'Introducida por profesional',
            self::Padron => 'Importada del padrón',
            self::Geocodificacion => 'Normalizada por geocoder',
        };
    }
}
