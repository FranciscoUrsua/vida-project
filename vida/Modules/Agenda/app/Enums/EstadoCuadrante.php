<?php

namespace Modules\Agenda\Enums;

use App\Support\Ui\Tono;

/**
 * Estados editoriales de un cuadrante mensual de agenda.
 */
enum EstadoCuadrante: string
{
    case Borrador = 'borrador';
    case Revision = 'revision';
    case Publicado = 'publicado';

    /**
     * Etiqueta legible para mostrar el estado del cuadrante.
     */
    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Revision => 'En revisión',
            self::Publicado => 'Publicado',
        };
    }

    /**
     * Color del badge de estado del cuadrante.
     *
     * @return Tono
     */
    public function tono(): Tono
    {
        return match ($this) {
            self::Publicado => Tono::Exito,
            self::Revision => Tono::Aviso,
            self::Borrador => Tono::Neutro,
        };
    }
}
