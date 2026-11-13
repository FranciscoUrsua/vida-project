<?php

namespace Modules\Centro\Enums;

/**
 * Clase de elemento del catálogo de un tipo de centro que un centro puede excluir
 * (RN-08): decide contra qué catálogo del tipo se valida la exclusión.
 */
enum ElementoCatalogo: string
{
    case Prestacion = 'prestacion';
    case TipoPlan = 'tipo_plan';
    case TipoFicha = 'tipo_ficha';

    /**
     * Etiqueta legible.
     */
    public function label(): string
    {
        return match ($this) {
            self::Prestacion => 'Prestación',
            self::TipoPlan => 'Tipo de plan',
            self::TipoFicha => 'Ficha de valoración',
        };
    }
}
