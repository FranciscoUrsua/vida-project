<?php

namespace App\Support\Menu;

use App\Models\User;
use Closure;

/**
 * Una entrada del menú operativo (R4.2): qué se pinta, adónde lleva, cuándo está activa y
 * qué permiso o policy la muestra.
 */
final class EntradaMenu
{
    /**
     * @param string $clave Identificador único: una entrada aparece una sola vez aunque la den varios roles.
     * @param string $seccion Clave de la sección (RegistroMenu::SECCIONES).
     * @param string $etiqueta Texto visible.
     * @param string $icono Nombre del Heroicon outline, sin prefijo.
     * @param Closure(User): string $url
     * @param Closure(): bool $activa Si la entrada corresponde a la pantalla actual.
     * @param Closure(User): bool $visible Permiso o policy que la muestra.
     * @param string|null $contador Clave del contador que lleva al lado (MenuOperativo::contador()).
     * @param bool $contadorAlerta Si el contador va en tono de alerta.
     */
    public function __construct(
        public readonly string $clave,
        public readonly string $seccion,
        public readonly string $etiqueta,
        public readonly string $icono,
        private readonly Closure $url,
        private readonly Closure $activa,
        private readonly Closure $visible,
        public readonly ?string $contador = null,
        public readonly bool $contadorAlerta = false,
    ) {}

    /**
     * Si el usuario ve la entrada.
     *
     * @param User $usuario
     *
     * @return bool
     */
    public function visiblePara(User $usuario): bool
    {
        return ($this->visible)($usuario);
    }

    /**
     * URL de la entrada para el usuario.
     *
     * @param User $usuario
     *
     * @return string
     */
    public function url(User $usuario): string
    {
        return ($this->url)($usuario);
    }

    /**
     * Si la entrada corresponde a la pantalla actual.
     *
     * @return bool
     */
    public function activa(): bool
    {
        return ($this->activa)();
    }
}
