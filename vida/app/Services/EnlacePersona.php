<?php

namespace App\Services;

use App\Models\HistoriaSocial;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;

/**
 * Adónde lleva un enlace a una persona, según lo que el usuario puede ver (R-02 de la ronda 1
 * de staging): la Historia Social si `HistoriaSocialPolicy::view` lo permite y, si no, la ficha
 * de la persona, cuyo acceso decide `FichaCiudadanoPage` con `CiudadanoPolicy`.
 *
 * Sustituye a los enlaces fijos a la Historia Social, que llevaban a un 403 a los roles sin
 * acceso a ella (`consulta_basica`, `tramitacion`).
 */
class EnlacePersona
{
    /**
     * URL de destino para una persona.
     *
     * @param User $usuario Usuario que sigue el enlace.
     * @param int $ciudadanoId Persona.
     *
     * @return string
     */
    public function url(User $usuario, int $ciudadanoId): string
    {
        $historia = HistoriaSocial::withoutGlobalScope(AmbitoUoScope::class)
            ->where('ciudadano_id', $ciudadanoId)
            ->where('estado', '!=', 'cerrada')
            ->latest()
            ->first();

        return $historia !== null && $usuario->can('view', $historia)
            ? route('intervencion.ciudadano.show', $historia->id)
            : route('ciudadania.ciudadano.ficha', $ciudadanoId);
    }
}
