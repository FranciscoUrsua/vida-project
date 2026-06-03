<?php

namespace Modules\Documentos\Enums;

enum TipoInforme: string
{
    case InformeSocial = 'informe_social';
    case InformePsicologico = 'informe_psicologico';
    case InformeJuridico = 'informe_juridico';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::InformeSocial => 'Informe social',
            self::InformePsicologico => 'Informe psicológico',
            self::InformeJuridico => 'Informe jurídico',
            self::Otro => 'Otro',
        };
    }
}
