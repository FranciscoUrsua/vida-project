<?php

namespace Modules\Prestaciones\Policies;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Prestaciones\Enums\EstadoSolicitudPrestacion;
use Modules\Prestaciones\Models\PrestacionPersona;
use Modules\Prestaciones\Models\SolicitudPrestacion;

/**
 * Quién ve y gestiona (inicio, suspensión, fin) las prestaciones de una persona (§5 del diseño).
 *
 * Las ve quien ve las solicitudes de la persona (`solicitud.ver`, con la regla de personas
 * protegidas). Las gestionan, con `prestacion_persona.gestionar`, quien las ve y tramitación de
 * la unidad que tramitó la solicitud de origen. `consulta_basica` no tiene ninguno de los dos.
 */
class PrestacionPersonaPolicy
{
    /**
     * Ver la prestación de la persona.
     *
     * @param User $usuario
     * @param PrestacionPersona $prestacion
     *
     * @return bool
     */
    public function view(User $usuario, PrestacionPersona $prestacion): bool
    {
        return $usuario->can('solicitud.ver') && Gate::forUser($usuario)->allows('view', $prestacion->ciudadano);
    }

    /**
     * Registrar inicio, suspender, reanudar o finalizar.
     *
     * @param User $usuario
     * @param PrestacionPersona $prestacion
     *
     * @return bool
     */
    public function gestionar(User $usuario, PrestacionPersona $prestacion): bool
    {
        if (! $usuario->can('prestacion_persona.gestionar')) {
            return false;
        }

        if ($this->view($usuario, $prestacion)) {
            return true;
        }

        // Tramitación: una solicitud resuelta de la persona en la bandeja de su unidad
        return SolicitudPrestacion::where('ciudadano_id', $prestacion->ciudadano_id)
            ->where('estado', EstadoSolicitudPrestacion::Resuelta->value)
            ->get()
            ->contains(fn (SolicitudPrestacion $s) => Gate::forUser($usuario)->allows('tramitar', $s));
    }
}
