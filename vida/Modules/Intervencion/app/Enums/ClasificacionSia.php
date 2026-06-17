<?php

namespace Modules\Intervencion\Enums;

/**
 * Clasificaciones SIA utilizadas para categorizar demandas de intervencion.
 */
enum ClasificacionSia: string
{
    case CompetenciaMunicipal = 'competencia_municipal';
    case OtraAdministracion = 'otra_administracion';
    case InformacionGeneral = 'informacion_general';

    /**
     * Etiqueta legible para mostrar la clasificacion SIA.
     */
    public function label(): string
    {
        return match ($this) {
            self::CompetenciaMunicipal => 'Competencia municipal',
            self::OtraAdministracion => 'Otra administración',
            self::InformacionGeneral => 'Información general',
        };
    }
}
