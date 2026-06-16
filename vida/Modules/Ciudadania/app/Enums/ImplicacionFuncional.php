<?php

namespace Modules\Ciudadania\Enums;

/**
 * Implicaciones funcionales de los tipos de relación entre ciudadanos.
 *
 * El código evalúa este campo para tomar decisiones de negocio (consentimientos,
 * notificaciones, representación). Nunca evalúa el slug ni la etiqueta de TipoRelacion.
 *
 * @see Modules\Ciudadania\Models\TipoRelacion
 */
enum ImplicacionFuncional: string
{
    case Representante     = 'representante';
    case TutorLegal        = 'tutor_legal';
    case CuidadorPrincipal = 'cuidador_principal';

    /**
     * Etiqueta legible para mostrar en backoffice.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Representante     => 'Representante',
            self::TutorLegal        => 'Tutor legal',
            self::CuidadorPrincipal => 'Cuidador principal',
        };
    }
}
