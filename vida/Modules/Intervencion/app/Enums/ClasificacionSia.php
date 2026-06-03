<?php

namespace Modules\Intervencion\Enums;

enum ClasificacionSia: string
{
    case CompetenciaMunicipal = 'competencia_municipal';
    case OtraAdministracion = 'otra_administracion';
    case InformacionGeneral = 'informacion_general';

    public function label(): string
    {
        return match ($this) {
            self::CompetenciaMunicipal => 'Competencia municipal',
            self::OtraAdministracion => 'Otra administración',
            self::InformacionGeneral => 'Información general',
        };
    }
}
