<?php

namespace Modules\Centro\Enums;

/**
 * Vía por la que una persona ocupa plaza en una sesión de actividad (fase 6, F6-2).
 *
 * Cada vía tiene su cupo en las actividades mixtas: `libre` la inscribe el personal
 * adscrito al centro de la actividad (RN-15); `prescripcion` nace de una prescripción
 * desde un plan, también desde otro centro.
 */
enum ViaInscripcion: string
{
    case Libre = 'libre';
    case Prescripcion = 'prescripcion';

    /**
     * Etiqueta para la interfaz.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Libre => 'Acceso libre',
            self::Prescripcion => 'Prescripción',
        };
    }

    /**
     * Si una actividad con ese modo de acceso admite la vía.
     *
     * @param string $modoAcceso libre | prescripcion | mixta
     *
     * @return bool
     */
    public function admitidaEn(string $modoAcceso): bool
    {
        return $modoAcceso === 'mixta' || $modoAcceso === $this->value;
    }
}
