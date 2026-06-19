<?php

namespace App\Enums;

/**
 * Tipos de acción registrables en la tabla de auditoría.
 *
 * @see docs/modulo-auditoria.md §2 y §5
 */
enum AccionAuditEnum: string
{
    case Ver = 'ver';
    case Crear = 'crear';
    case Editar = 'editar';
    case Eliminar = 'eliminar';
    case Exportar = 'exportar';
    case Imprimir = 'imprimir';
    case AccesoRestringido = 'acceso_restringido';

    /** Etiqueta en lenguaje natural para la vista del ciudadano.
     *
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Ver => 'Consultó el expediente',
            self::Crear => 'Registró nueva información',
            self::Editar => 'Modificó información existente',
            self::Eliminar => 'Eliminó un registro',
            self::Exportar => 'Exportó datos',
            self::Imprimir => 'Generó un documento',
            self::AccesoRestringido => 'Accedió a expediente con protección especial',
        };
    }

    /** Color semántico para el badge en Filament.
     *
     */
    public function color(): string
    {
        return match ($this) {
            self::Ver => 'gray',
            self::Crear => 'success',
            self::Editar => 'warning',
            self::Eliminar => 'danger',
            self::Exportar => 'info',
            self::Imprimir => 'info',
            self::AccesoRestringido => 'danger',
        };
    }
}
