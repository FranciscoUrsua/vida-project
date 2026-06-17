<?php

namespace Modules\Documentos\Enums;

/**
 * Tipos funcionales de informe que puede gestionar el modulo de documentos.
 */
enum TipoInforme: string
{
    case InformeSocial = 'informe_social';
    case InformePsicologico = 'informe_psicologico';
    case InformeJuridico = 'informe_juridico';
    case Otro = 'otro';

    /**
     * Etiqueta legible para mostrar el tipo de informe.
     *
     * @return string
     */
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
