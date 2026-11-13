<?php

namespace App\Support\Ui;

/**
 * Tono de color con el que se pinta un estado o un tipo (badges, puntos, bloques).
 *
 * Es la única fuente de las combinaciones de color de Bootstrap que antes se
 * copiaban en cada vista (`bg-success-subtle text-success-emphasis`…). Los
 * enums y las tablas de presentación de cada módulo dicen qué tono tiene cada
 * valor; las clases salen siempre de aquí, completas y sin concatenar.
 */
enum Tono: string implements FuenteClasesCss
{
    case Primario = 'primary';
    case Exito = 'success';
    case Aviso = 'warning';
    case Peligro = 'danger';
    case Info = 'info';
    case Neutro = 'secondary';
    case Protegido = 'protected';

    /**
     * Fondo suave con texto de énfasis: badges de estado y de tipo.
     *
     * @return string
     */
    public function clasesSuave(): string
    {
        return match ($this) {
            self::Primario => 'bg-primary-subtle text-primary-emphasis',
            self::Exito => 'bg-success-subtle text-success-emphasis',
            self::Aviso => 'bg-warning-subtle text-warning-emphasis',
            self::Peligro => 'bg-danger-subtle text-danger-emphasis',
            self::Info => 'bg-info-subtle text-info-emphasis',
            self::Neutro => 'bg-secondary-subtle text-secondary-emphasis',
            self::Protegido => 'bg-protected-subtle text-protected-emphasis',
        };
    }

    /**
     * Fondo lleno con texto de contraste: badges que tienen que destacar.
     *
     * @return string
     */
    public function clasesFuerte(): string
    {
        return match ($this) {
            self::Primario => 'text-bg-primary',
            self::Exito => 'text-bg-success',
            self::Aviso => 'text-bg-warning',
            self::Peligro => 'text-bg-danger',
            self::Info => 'text-bg-info',
            self::Neutro => 'text-bg-secondary',
            self::Protegido => 'text-bg-protected',
        };
    }

    /**
     * Solo el fondo lleno: puntos y muestras de color sin texto.
     *
     * @return string
     */
    public function clasesPunto(): string
    {
        return match ($this) {
            self::Primario => 'bg-primary',
            self::Exito => 'bg-success',
            self::Aviso => 'bg-warning',
            self::Peligro => 'bg-danger',
            self::Info => 'bg-info',
            self::Neutro => 'bg-secondary',
            self::Protegido => 'bg-protected',
        };
    }

    /**
     * Fondo suave con borde del color: bloques de la agenda (el grosor y el lado
     * del borde los pone la vista).
     *
     * @return string
     */
    public function clasesBloque(): string
    {
        return match ($this) {
            self::Primario => 'bg-primary-subtle border-primary',
            self::Exito => 'bg-success-subtle border-success',
            self::Aviso => 'bg-warning-subtle border-warning',
            self::Peligro => 'bg-danger-subtle border-danger',
            self::Info => 'bg-info-subtle border-info',
            self::Neutro => 'bg-secondary-subtle border-secondary',
            self::Protegido => 'bg-protected-subtle border-protected',
        };
    }

    /**
     * Todas las clases que puede devolver cualquier tono.
     *
     * @return list<string>
     */
    public static function clasesCss(): array
    {
        $clases = [];
        foreach (self::cases() as $tono) {
            foreach ([$tono->clasesSuave(), $tono->clasesFuerte(), $tono->clasesPunto(), $tono->clasesBloque()] as $grupo) {
                array_push($clases, ...explode(' ', $grupo));
            }
        }

        return array_values(array_unique($clases));
    }
}
