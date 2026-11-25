<?php

namespace App\Support\Superficie;

use App\Models\User;

/**
 * Único resolutor de la página de inicio de un usuario (R4.7).
 *
 * Orden: preferencia guardada y todavía accesible → prioridad de superficies (R4.5) →
 * administración, solo si no tiene ninguna superficie operativa → página informativa de
 * usuario sin perfil de trabajo. Siempre devuelve un destino: nunca hay una redirección sin
 * destino (EST-02, ronda 1 de staging). Lo usan el login, el primer acceso, la ruta `/`
 * (logo, «Inicio» y «Volver al inicio» de las páginas de error).
 */
final class DestinoInicial
{
    /**
     * URL de la página de inicio del usuario.
     *
     * @param User $usuario
     *
     * @return string
     */
    public static function para(User $usuario): string
    {
        return self::superficie($usuario)?->url()
            ?? ($usuario->canAccessPanel(filament()->getPanel('admin')) ? url('/admin') : route('sin-rol'));
    }

    /**
     * Superficie operativa en la que entra el usuario, o null si no tiene ninguna.
     *
     * Una preferencia que ya no es accesible se ignora sin aviso y no se borra: si el usuario
     * recupera el acceso, vuelve a valer (R4.6).
     *
     * @param User $usuario
     *
     * @return Superficie|null
     */
    public static function superficie(User $usuario): ?Superficie
    {
        $preferida = $usuario->pagina_inicio !== null ? Superficie::tryFrom($usuario->pagina_inicio) : null;
        if ($preferida !== null && $preferida->accesiblePara($usuario)) {
            return $preferida;
        }

        foreach (Superficie::prioridad() as $superficie) {
            if ($superficie->inicioPorDefectoPara($usuario)) {
                return $superficie;
            }
        }

        return null;
    }

    /**
     * Superficies que el usuario puede elegir como página de inicio (R4.6). Vacío si solo tiene
     * una superficie operativa: entonces la opción no se muestra.
     *
     * Superficies operativas son las de la prioridad (R4.5). El buscador es elegible, pero no
     * cuenta como una más: la ventanilla (citación y buscador) no ve la opción (TF-STG-28).
     *
     * @param User $usuario
     *
     * @return list<Superficie>
     */
    public static function opcionesPreferencia(User $usuario): array
    {
        $operativas = array_filter(Superficie::prioridad(), fn (Superficie $s) => $s->accesiblePara($usuario));

        return count($operativas) > 1 ? Superficie::accesiblesPara($usuario) : [];
    }
}
