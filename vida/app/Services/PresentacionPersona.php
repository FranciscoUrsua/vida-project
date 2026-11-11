<?php

namespace App\Services;

use App\Models\Ciudadano;
use App\Models\User;

/**
 * Presenta a una persona que aparece en un listado de otra (conviviente, relación,
 * representante): su nombre y su enlace, o la versión protegida.
 *
 * Restricción de dominio (CLAUDE.md §3): un colectivo protegido no es accesible
 * fuera de su UO responsable sin aprobación. Por eso, si la persona es de un
 * colectivo protegido que la policy no deja ver al usuario, se muestra que la
 * relación existe pero no quién es: «Persona con protección especial», sin
 * documento, fecha de nacimiento, domicilio, alias, contacto ni enlace a su ficha.
 * El criterio es el único del proyecto: `CiudadanoPolicy::view()` (que delega en
 * `consultaExternaPermitida()`).
 */
class PresentacionPersona
{
    /** Texto que sustituye al nombre de una persona protegida que no se puede ver. */
    public const NOMBRE_PROTEGIDO = 'Persona con protección especial';

    /**
     * Si el usuario puede ver la identidad de la persona.
     *
     * Solo se oculta a un colectivo protegido que la policy no deja ver; el resto de
     * personas relacionadas se muestran siempre, como hasta ahora.
     *
     * Sin usuario (p. ej. un proceso sin sesión) un colectivo protegido nunca se muestra.
     *
     * @param Ciudadano $ciudadano Persona relacionada.
     * @param User|null $usuario Usuario que consulta.
     *
     * @return bool
     */
    public static function visible(Ciudadano $ciudadano, ?User $usuario): bool
    {
        return ! $ciudadano->colectivo_extra_protegido || ($usuario !== null && $usuario->can('view', $ciudadano));
    }

    /**
     * Datos de la persona para pintarla en un listado.
     *
     * Con `visible` a false solo se devuelve el texto de persona protegida: ni id,
     * ni enlace, ni el modelo, para que la vista no pueda mostrar nada más.
     *
     * @param Ciudadano|null $ciudadano Persona relacionada (null si no se pudo cargar).
     * @param User|null $usuario Usuario que consulta.
     *
     * @return array{visible: bool, id: int|null, nombre: string, url: string|null, ciudadano: Ciudadano|null}
     */
    public static function paraListado(?Ciudadano $ciudadano, ?User $usuario): array
    {
        if ($ciudadano === null) {
            return ['visible' => false, 'id' => null, 'nombre' => '—', 'url' => null, 'ciudadano' => null];
        }

        if (! self::visible($ciudadano, $usuario)) {
            return ['visible' => false, 'id' => null, 'nombre' => self::NOMBRE_PROTEGIDO, 'url' => null, 'ciudadano' => null];
        }

        return [
            'visible' => true,
            'id' => $ciudadano->id,
            'nombre' => $ciudadano->nombre_completo,
            'url' => route('ciudadania.ciudadano.ficha', $ciudadano->id),
            'ciudadano' => $ciudadano,
        ];
    }
}
